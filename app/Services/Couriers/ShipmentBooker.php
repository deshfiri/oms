<?php

namespace App\Services\Couriers;

use App\Models\CourierConsignment;
use App\Models\OrderMirror;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Support\Facades\Log;

/**
 * Registers a courier consignment for an order — REAL courier data only.
 *
 * The consignment id (and tracking code) come from the actual courier API,
 * NEVER fabricated, NEVER entered by hand. If the API does not return a real
 * consignment, this THROWS {@see CourierBookingFailedException} and creates
 * nothing — the order stays in Processing for the operator to fix and retry.
 *
 * Source of truth, in order:
 *   1. The store website's Courier API (POST /orders/{n}/shipments) — registers
 *      the parcel and returns the real consignment id + tracking.
 *   2. A direct OMS adapter that can produce real data (Steadfast w/ keys).
 *   3. One last read of the order from the website (in case it registered async).
 *   4. Otherwise → throw. No consignment, no status change.
 */
class ShipmentBooker
{
    public function __construct(
        protected CourierManager $couriers,
        protected CourierPicker $picker,
    ) {}

    public function book(OrderMirror $order): CourierConsignment
    {
        $cod  = $order->payment_method === 'cod' ? (float) $order->grand_total : 0.0;
        $slug = $this->picker->pickFor($order);   // honours the order's store-website courier
        $bookingError = null;

        // ── 1. Register via the store website's courier API ──────────────
        try {
            $remote = $this->bookViaStorefront($order, $slug, $cod);
            if ($remote) {
                return CourierConsignment::create([
                    'order_id'       => $order->id,
                    'courier_slug'   => $remote['courier_slug'] ?: $slug,
                    'courier_name'   => $remote['courier_name'] ?: $this->name($slug),
                    'consignment_id' => $remote['consignment_id'],
                    'tracking_code'  => $remote['tracking_code'],
                    'tracking_url'   => $remote['tracking_url'],
                    'label_url'      => $remote['label_url'],
                    'cod_amount'     => $cod,
                    'latest_status'  => 'booked',
                    'raw_payload'    => $remote['raw'] + ['source' => 'storefront_api'],
                    'booked_at'      => now(),
                ]);
            }
        } catch (\Throwable $e) {
            $bookingError = $this->cleanCourierError($e->getMessage());
            Log::warning('Storefront courier registration failed', ['order' => $order->order_number, 'err' => $e->getMessage()]);
        }

        // ── 2. A direct adapter that can produce REAL ids (e.g. Steadfast) ─
        $adapter = $this->couriers->adapter($slug);
        if (($adapter->capabilities()['direct_api'] ?? false)) {
            try {
                $book = $adapter->bookConsignment($order, ['cod_amount' => $cod]);
                if (! empty($book['consignment_id']) || ! empty($book['tracking_code'])) {
                    $this->recordOnStorefront($order, $slug, $book, $cod);

                    return CourierConsignment::create([
                        'order_id'       => $order->id,
                        'courier_slug'   => $adapter->slug(),
                        'courier_name'   => $adapter->displayName(),
                        'consignment_id' => $book['consignment_id'],
                        'tracking_code'  => $book['tracking_code'],
                        'tracking_url'   => $book['tracking_url'],
                        'label_url'      => $book['label_url'],
                        'cod_amount'     => $cod,
                        'latest_status'  => 'booked',
                        'raw_payload'    => ($book['raw'] ?? []) + ['source' => 'oms_direct'],
                        'booked_at'      => now(),
                    ]);
                }
            } catch (CourierUnavailableException $e) {
                Log::info('Direct courier booking unavailable', ['order' => $order->order_number, 'err' => $e->getMessage()]);
            } catch (\Throwable $e) {
                $bookingError = $this->cleanCourierError($e->getMessage());
                Log::warning('Direct courier booking failed', ['order' => $order->order_number, 'err' => $e->getMessage()]);
            }
        }

        // ── 3. Last chance: the website may have registered it a beat later. ─
        if ($fresh = $this->pullFreshConsignment($order)) {
            return $fresh;
        }

        // ── 4. No real consignment → FAIL. Nothing is created, status unchanged.
        throw new CourierBookingFailedException(
            $bookingError
                ?: 'The courier API did not return a consignment. The order stays in Processing — fix the courier/account, then send to courier again.'
        );
    }

    /**
     * Immediately re-pull the order from the store website so the just-
     * registered consignment lands without waiting for the next sync. The
     * storefront embeds courier data in the order's `shipments[]`, so we fetch
     * the order and re-mirror it (which folds shipments into consignments).
     */
    protected function pullFreshConsignment(OrderMirror $order): ?CourierConsignment
    {
        if (! $order->store) return null;
        $client   = new StorefrontClient($order->store);
        $upserter = app(\App\Services\Mirror\MirrorUpserter::class);
        $filled   = false;

        foreach ([
            fn () => $client->orders()->find($order->order_number),
            fn () => $client->orders()->list(['order_number' => $order->order_number]),
        ] as $attempt) {
            try {
                $resp = $attempt();
                if (is_array($resp) && $upserter->applyShipments($order->fresh(), $resp)) {
                    $filled = true;
                    break;
                }
            } catch (\Throwable $e) {
                Log::info('Immediate consignment pull attempt failed', ['order' => $order->order_number, 'err' => $e->getMessage()]);
            }
        }

        if (! $filled) {
            try { \App\Jobs\SyncShipmentsJob::dispatchSync($order->store->id); } catch (\Throwable) {}
        }

        $fresh = $order->fresh()->consignments()->latest('id')->first();
        return ($fresh && $fresh->consignment_id) ? $fresh : null;
    }

    private function name(string $slug): string
    {
        try { return $this->couriers->adapter($slug)->displayName(); }
        catch (\Throwable) { return ucfirst($slug); }
    }

    /** Pull the human part out of a courier error string for display. */
    private function cleanCourierError(string $msg): string
    {
        // e.g. "Steadfast booking failed: Account is not active!" → keep as-is,
        // but trim any trailing JSON/HTML noise.
        $msg = trim(preg_replace('/\s+/', ' ', strip_tags($msg)));
        return mb_strimwidth($msg, 0, 180, '…');
    }

    /**
     * Record an OMS-booked shipment back on the store website so the consignment
     * + tracking also appear there. Best-effort — never blocks the OMS booking.
     */
    protected function recordOnStorefront(OrderMirror $order, string $slug, array $book, float $cod): void
    {
        if (! $order->store) return;
        try {
            (new StorefrontClient($order->store))->orders()->createShipment(
                $order->order_number,
                array_filter([
                    'courier'        => $slug,
                    'tracking_code'  => $book['tracking_code']  ?? null,
                    'consignment_id' => $book['consignment_id'] ?? null,
                    'cod_amount'     => $cod,
                ], fn ($v) => $v !== null),
            );
        } catch (\Throwable $e) {
            Log::info('Recording shipment on storefront failed', ['order' => $order->order_number, 'err' => $e->getMessage()]);
        }
    }

    /**
     * Returns a normalized array of REAL courier data on success, or null when
     * the store website can't provide it (so the caller goes pending).
     *
     * @return array{courier_slug:?string,courier_name:?string,consignment_id:?string,tracking_code:?string,tracking_url:?string,label_url:?string,raw:array}|null
     */
    protected function bookViaStorefront(OrderMirror $order, string $slug, float $cod): ?array
    {
        if (! $order->store) return null;

        try {
            $resp = (new StorefrontClient($order->store))->orders()->createShipment(
                $order->order_number,
                [
                    'courier'    => $slug,
                    'cod_amount' => $cod,
                ],
            );
        } catch (\Throwable $e) {
            Log::info('Storefront shipment booking unavailable', [
                'order' => $order->order_number, 'err' => $e->getMessage(),
            ]);
            return null;
        }

        $d = $resp['data'] ?? $resp;

        // The response may be the shipment itself, or the order carrying a
        // `shipments[]` array (DFCOMMERCE shape) — pick the newest shipment.
        if (empty($d['consignment_id']) && empty($d['consignmentId']) && empty($d['tracking_code'])) {
            $list = $d['shipments'] ?? $d['consignments'] ?? $resp['shipments'] ?? null;
            if (is_array($list) && $list) {
                $d = end($list); // newest
            }
        }

        $consignment = $d['consignment_id'] ?? $d['consignmentId'] ?? null;
        $tracking    = $d['tracking_code'] ?? $d['trackingCode'] ?? $d['tracking'] ?? null;

        // Storefront responded but gave nothing usable ⇒ go pending (no fake).
        if (! $consignment && ! $tracking) return null;

        return [
            'courier_slug'   => $d['courier_slug'] ?? $d['courier'] ?? $slug,
            'courier_name'   => $d['courier_name'] ?? null,
            'consignment_id' => $consignment,
            'tracking_code'  => $tracking,
            'tracking_url'   => $d['tracking_url'] ?? $d['trackingUrl'] ?? null,
            'label_url'      => $d['label_url'] ?? $d['labelUrl'] ?? null,
            'raw'            => is_array($resp) ? $resp : [],
        ];
    }
}
