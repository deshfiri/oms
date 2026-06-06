<?php

namespace App\Http\Controllers;

use App\Models\Exchange;
use App\Models\OrderMirror;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExchangeController extends Controller
{
    public function index()
    {
        $exchanges = Exchange::with('original.store:id,dfid,business_name,name','replacement','requester')
            ->latest()->paginate(30);
        return view('exchanges.index', compact('exchanges'));
    }

    public function show(Exchange $exchange)
    {
        $exchange->load('original.items','replacement.items','requester');
        return view('exchanges.show', compact('exchange'));
    }

    /** Exchange request form: pick the NEW product(s) to send as the replacement. */
    public function create(OrderMirror $order)
    {
        if (! in_array($order->status, [OrderStateMachine::DELIVERED, OrderStateMachine::PARTIAL_DELIVERY], true)) {
            return redirect()->route('orders.show', $order)
                ->with('error', "{$order->order_number} is {$order->statusLabel()} — only delivered orders can be exchanged.");
        }
        $order->load('items', 'store');
        return view('exchanges.create', compact('order'));
    }

    /** Pull the human message out of a storefront API error string. */
    private function cleanError(string $msg): string
    {
        $j = json_decode($msg, true);
        if (is_array($j) && ! empty($j['message'])) return $j['message'];
        return \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($msg))), 160);
    }

    /** AJAX: search the order's store catalogue for the replacement product. */
    public function productSearch(Request $r, OrderMirror $order)
    {
        $rows = \App\Models\ProductMirror::where('store_id', $order->store_id)
            ->when($r->q, fn ($q, $t) => $q->where(fn ($x) => $x->where('sku', 'like', "%$t%")->orWhere('name', 'like', "%$t%")))
            ->orderBy('name')->limit(20)->get(['sku', 'name', 'price', 'sale_price', 'stock_quantity']);
        return response()->json($rows);
    }

    /**
     * Complete an exchange: release the replacement order into the warehouse
     * (→ Processing, ready to send to courier) and close the exchange.
     */
    public function complete(Exchange $exchange)
    {
        abort_if(in_array($exchange->status, ['completed', 'cancelled'], true), 422);

        if ($exchange->replacement && $exchange->replacement->status === OrderStateMachine::EXCHANGE_PROCESSING) {
            $exchange->replacement->forceFill([
                'status'        => OrderStateMachine::PROCESSING,
                'confirmed_at'  => $exchange->replacement->confirmed_at ?? now(),
                'processing_at' => now(),
            ])->save();
        }

        $exchange->update(['status' => 'completed', 'completed_at' => now()]);

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(), 'action' => 'exchange.complete',
            'entity_type' => Exchange::class, 'entity_id' => $exchange->id,
            'after_json' => ['status' => 'completed'], 'ip' => request()?->ip(),
        ]);

        return back()->with('status', 'Exchange completed — replacement order released to Processing.');
    }

    /** Cancel an exchange and its replacement order. */
    public function cancel(Exchange $exchange)
    {
        abort_if(in_array($exchange->status, ['completed', 'cancelled'], true), 422);

        if ($exchange->replacement) {
            $exchange->replacement->forceFill([
                'status'        => OrderStateMachine::CANCELLED,
                'cancelled_at'  => now(),
                'cancel_reason' => 'Exchange cancelled',
            ])->save();
        }

        $exchange->update(['status' => 'cancelled', 'completed_at' => now()]);

        return back()->with('status', 'Exchange cancelled — replacement order cancelled.');
    }

    /** Open an exchange request linked to an existing order. */
    /**
     * Issue an exchange for a delivered order:
     *   • a REPLACEMENT order is created straight in Processing (registered on
     *     the store website so it can be fulfilled like any order), and
     *   • the ORIGINAL order moves to Awaiting Return Product — pending the old
     *     item coming back, where it's received & inspected.
     */
    public function open(Request $r, OrderMirror $order, OrderStateMachine $sm)
    {
        $data = $r->validate([
            'reason'        => 'required|string|max:80',
            'notes'         => 'nullable|string|max:2000',
            'items'         => 'required|array|min:1',
            'items.*.sku'   => 'required|string|max:255',
            'items.*.qty'   => 'required|integer|min:1',
        ]);

        if (! in_array($order->status, [OrderStateMachine::DELIVERED, OrderStateMachine::PARTIAL_DELIVERY], true)) {
            return back()->with('error', "{$order->order_number} is {$order->statusLabel()} — only delivered orders can be exchanged.");
        }

        try {
            $replacement = $this->makeReplacement($order, $data['items']);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error',
                'Could not register the replacement on the store: '.$e->getMessage().' — pick a product that exists on the store, then try again.');
        }

        $ex = Exchange::create([
            'original_order_id'    => $order->id,
            'replacement_order_id' => $replacement->id,
            'reason'               => $data['reason'],
            'notes'                => $data['notes'] ?? null,
            'status'               => 'processing',
            'requested_by'         => auth()->id(),
            'requested_at'         => now(),
        ]);

        try {
            // Original → pending return for exchange.
            $sm->transition($order, OrderStateMachine::EXCHANGE_REQUESTED, ['note' => $data['notes'] ?? null]);
            $sm->transition($order->refresh(), OrderStateMachine::AWAITING_RETURN_PRODUCT, [
                'note' => 'Awaiting the old product back (exchange) for inspection',
            ]);
        } catch (\Throwable $e) {
            $ex->delete();
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('exchanges.show', $ex)->with('status',
            "Exchange issued — replacement {$replacement->order_number} is in Processing; {$order->order_number} now awaits the returned product.");
    }

    /**
     * Build the replacement order in Processing. Registers it on the store
     * website (status=processing, source=oms_exchange) so it can be fulfilled
     * with the website courier API; falls back to an OMS-local clone if the
     * website is unreachable.
     */
    protected function makeReplacement(OrderMirror $order, array $items): OrderMirror
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
                'note'           => 'Exchange replacement for '.$order->order_number,
            ];
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
                throw new \RuntimeException($this->cleanError($e->getMessage()));
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
