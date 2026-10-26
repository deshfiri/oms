<?php

namespace App\Services\Orders;

use App\Models\Exchange;
use App\Models\OrderMirror;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Issues an exchange for a delivered order — shared by the order-detail
 * "Issue exchange" page and the Register order form (exchange mode):
 *   • a REPLACEMENT order is created straight in Processing (registered on
 *     the store website so it can be fulfilled like any order), and
 *   • the ORIGINAL order moves to Awaiting Return Product — pending the old
 *     item coming back, where it's received & inspected.
 */
class ExchangeIssuer
{
    public const REASONS = [
        'wrong_item'   => 'Wrong item delivered',
        'size_issue'   => 'Size / fit issue',
        'defective'    => 'Defective / not working',
        'damaged'      => 'Damaged',
        'changed_mind' => 'Changed selection',
    ];

    public function __construct(protected OrderStateMachine $sm) {}

    public static function canExchange(OrderMirror $order): bool
    {
        return in_array($order->status, [OrderStateMachine::DELIVERED, OrderStateMachine::PARTIAL_DELIVERY], true);
    }

    /**
     * @param  array  $items  [['sku' => ..., 'qty' => ...], ...] — the replacement products
     * @param  array  $extra  optional replacement-order fields: discount, note
     * @throws ExchangeException when the order can't be exchanged or the store rejects the replacement
     */
    public function issue(OrderMirror $order, array $items, string $reason, ?string $notes = null, array $extra = []): Exchange
    {
        // One exchange at a time per original order — a double-click must not
        // register two replacement orders on the store.
        $lock = Cache::lock("exchange:issue:{$order->id}", 60);
        if (! $lock->get()) {
            throw new ExchangeException("An exchange for {$order->order_number} is already being created — check the Exchanges list before retrying.");
        }

        try {
            $order->refresh();
            if (! self::canExchange($order)) {
                throw new ExchangeException("{$order->order_number} is {$order->statusLabel()} — only delivered orders can be exchanged.");
            }

            try {
                $replacement = $this->makeReplacement($order, $items, $extra);
            } catch (\Throwable $e) {
                throw new ExchangeException('Could not register the replacement on the store: '.$e->getMessage().' — pick a product that exists on the store, then try again.');
            }

            $ex = Exchange::create([
                'original_order_id'    => $order->id,
                'replacement_order_id' => $replacement->id,
                'reason'               => $reason,
                'notes'                => $notes,
                'status'               => 'processing',
                'requested_by'         => auth()->id(),
                'requested_at'         => now(),
            ]);

            try {
                // Original → pending return for exchange.
                $this->sm->transition($order, OrderStateMachine::EXCHANGE_REQUESTED, ['note' => $notes]);
                $this->sm->transition($order->refresh(), OrderStateMachine::AWAITING_RETURN_PRODUCT, [
                    'note' => 'Awaiting the old product back (exchange) for inspection',
                ]);
            } catch (\Throwable $e) {
                $ex->delete();
                throw new ExchangeException($e->getMessage());
            }

            return $ex;
        } finally {
            $lock->release();
        }
    }

    /** Pull the human message out of a storefront API error string. */
    public static function cleanError(string $msg): string
    {
        $j = json_decode($msg, true);
        if (is_array($j) && ! empty($j['message'])) return $j['message'];
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($msg))), 160);
    }

    /**
     * Build the replacement order in Processing. Registers it on the store
     * website (status=processing, source=oms_exchange) so it can be fulfilled
     * with the website courier API; falls back to an OMS-local clone if the
     * order has no store.
     */
    protected function makeReplacement(OrderMirror $order, array $items, array $extra = []): OrderMirror
    {
        $lines = array_map(fn ($line) => array_filter([
            'sku'      => $line['sku'],
            'quantity' => (int) $line['qty'],
        ], fn ($v) => $v !== null), $items);

        if ($order->store) {
            $payload = [
                'customer' => array_filter([
                    'name'  => $order->customer_name,
                    'phone' => $order->customer_phone,
                    'email' => $order->customer_email,
                ], fn ($v) => $v !== null && $v !== ''),
                'shipping' => array_filter([
                    'name'     => $order->shipping_name ?: $order->customer_name,
                    'phone'    => $order->shipping_phone ?: $order->customer_phone,
                    'address'  => $order->shipping_address_line,
                    'area'     => $order->shipping_area,
                    'city'     => $order->shipping_city,
                    'district' => $order->shipping_district,
                    'postcode' => $order->shipping_postcode,
                    'zone'     => $order->shipping_zone,
                ], fn ($v) => $v !== null && $v !== ''),
                'items'          => $lines,
                'payment_method' => $order->payment_method ?: 'cod',
                'shipping_total' => 0, // free re-delivery on exchange
                'status'         => 'processing', // skip verification — go straight to the warehouse
                'source'         => 'oms_exchange',          // marks this as an EXCHANGE order
                'is_exchange'    => true,                    // explicit flag for the store
                'exchange_of'    => $order->order_number,    // the original order it replaces
                'placed_by'      => auth()->user()?->name,
                'note'           => trim('Exchange replacement for '.$order->order_number.(! empty($extra['note']) ? ' — '.$extra['note'] : '')),
            ];
            if (! empty($extra['discount'])) {
                $payload['discount'] = (float) $extra['discount'];
            }
            try {
                $resp   = (new \App\Services\Storefront\StorefrontClient($order->store))->orders()->create($payload);
                $remote = $resp['data'] ?? $resp;
                if (! empty($remote['order_number'])) {
                    $rep = app(\App\Services\Mirror\MirrorUpserter::class)->order($order->store, $remote);
                    $rep->update(['exchange_of_order_id' => $order->id, 'placed_by_user_id' => auth()->id()]);
                    return $rep;
                }
                throw new \RuntimeException('the store did not return an order number');
            } catch (\Throwable $e) {
                // The store is reachable but rejected the replacement (e.g. the
                // product isn't on the store). Surface it — never create an
                // unfulfillable local clone that can't be sent to courier.
                \Log::warning('Exchange replacement registration failed', ['order' => $order->order_number, 'err' => $e->getMessage()]);
                throw new \RuntimeException(self::cleanError($e->getMessage()));
            }
        }

        // Fallback: OMS-local replacement, directly in Processing, with the chosen items.
        $rep = $order->replicate(['raw_payload']);
        $rep->order_number         = $order->order_number.'-EX-'.Str::upper(Str::random(4));
        $rep->status               = OrderStateMachine::PROCESSING;
        $rep->exchange_of_order_id = $order->id;
        $rep->confirmed_at         = now();
        $rep->processing_at        = now();
        $rep->placed_at            = now();
        $rep->shipping_total       = 0;
        $rep->save();

        $subtotal = 0;
        foreach ($items as $line) {
            $p = \App\Models\ProductMirror::where('store_id', $order->store_id)->where('sku', $line['sku'])->first();
            if (! $p) continue;
            $unit  = (float) ($p->sale_price ?? $p->price);
            $total = $unit * (int) $line['qty'];
            $subtotal += $total;
            $rep->items()->create([
                'product_id' => $p->product_id, 'sku' => $p->sku, 'name' => $p->name,
                'qty' => (int) $line['qty'], 'unit_price' => $unit, 'line_total' => $total,
            ]);
        }
        $rep->update(['subtotal' => $subtotal, 'grand_total' => $subtotal]);
        return $rep;
    }
}
