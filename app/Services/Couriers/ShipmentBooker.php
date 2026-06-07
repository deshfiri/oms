<?php

namespace App\Services\Couriers;

use App\Models\CourierConsignment;
use App\Models\OrderMirror;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Support\Facades\Log;

/**
 * Registers a courier consignment for an order — REAL courier data only.
 *
 * Flow:
 *   0. Validate store config (courier enabled, credentials present). Hard-fail
 *      with a clear error if anything is wrong — no silent courier substitution.
 *   stub. If courier_mode = 'stub', create a synthetic consignment and stop.
 *   1. Register via the store website's courier API.
 *   2. A direct OMS adapter that can produce real IDs (Steadfast with keys).
 *   3. Re-pull the order from the store website in case it registered async.
 *   4. Throw — order stays in Processing, nothing is created.
 *
 * The configured courier is the only courier ever used. No automatic fallback
 * to a different courier is allowed.
 */
class ShipmentBooker
{
    public function __construct(
        protected CourierManager  $couriers,
        protected CourierPicker   $picker,
        protected CourierValidator $validator,
    ) {}

    public function book(OrderMirror $order): CourierConsignment
    {
        $cod  = $order->payment_method === 'cod' ? (float) $order->grand_total : 0.0;
        $slug = $this->picker->pickFor($order);

        Log::info('[ShipmentBooker] starting dispatch', [
            'order'                 => $order->order_number,
            'store_id'              => $order->store?->id,
            'store_default_courier' => $order->store?->default_courier,
            'courier_enabled'       => $order->store?->courier_enabled,
            'courier_mode'          => $order->store?->courier_mode ?? 'live',
            'picked_slug'           => $slug,
            'cod'                   => $cod,
        ]);

        // ── 0. Validate config — hard-fail before touching any courier API ──
        try {
            $this->validator->validate($order, $slug);
        } catch (CourierConfigException $e) {
            Log::error('[ShipmentBooker] config validation failed', ['order' => $order->order_number, 'reason' => $e->getMessage()]);
            throw new CourierBookingFailedException($e->getMessage());
        }

        // ── Stub mode — no real API call, synthetic consignment only ─────────
        $mode = $order->store?->courier_mode ?? 'live';
        if ($mode === 'stub') {
            return $this->createStubConsignment($order, $slug, $cod);
        }

        $bookingError = null;

        // ── 1. Register via the store website's courier API ──────────────────
        try {
            Log::info('[ShipmentBooker] attempting storefront booking', ['order' => $order->order_number, 'courier' => $slug]);
            $remote = $this->bookViaStorefront($order, $slug, $cod);
            if ($remote) {
                $storefrontSlug = $remote['courier_slug'] ?? null;
                if ($storefrontSlug && $storefrontSlug !== $slug) {
                    Log::warning('[ShipmentBooker] storefront returned different courier slug — keeping OMS-configured slug', [
                        'order'           => $order->order_number,
                        'oms_slug'        => $slug,
                        'storefront_slug' => $storefrontSlug,
                    ]);
                }
                Log::info('[ShipmentBooker] storefront booking succeeded', ['order' => $order->order_number, 'slug' => $slug, 'consignment' => $remote['consignment_id']]);
                return CourierConsignment::create([
                    'order_id'       => $order->id,
                    'courier_slug'   => $slug,
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
            Log::warning('[ShipmentBooker] storefront booking threw', ['order' => $order->order_number, 'err' => $e->getMessage()]);
        }

        // ── 2. Direct adapter (e.g. Steadfast when keys are configured) ───────
        $adapter = $this->couriers->adapter($slug);
        $caps    = $adapter->capabilities();
        Log::info('[ShipmentBooker] checking direct adapter', [
            'order'      => $order->order_number,
            'slug'       => $slug,
            'adapter'    => get_class($adapter),
            'direct_api' => $caps['direct_api'] ?? false,
        ]);

        if ($caps['direct_api'] ?? false) {
            try {
                Log::info('[ShipmentBooker] attempting direct adapter booking', ['order' => $order->order_number, 'adapter' => get_class($adapter)]);
                $book = $adapter->bookConsignment($order, ['cod_amount' => $cod]);
                if (! empty($book['consignment_id']) || ! empty($book['tracking_code'])) {
                    $this->recordOnStorefront($order, $slug, $book, $cod);
                    Log::info('[ShipmentBooker] direct booking succeeded', ['order' => $order->order_number, 'slug' => $slug, 'consignment' => $book['consignment_id']]);
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
                Log::info('[ShipmentBooker] direct adapter unavailable', ['order' => $order->order_number, 'err' => $e->getMessage()]);
            } catch (\Throwable $e) {
                $bookingError = $this->cleanCourierError($e->getMessage());
                Log::warning('[ShipmentBooker] direct adapter threw', ['order' => $order->order_number, 'err' => $e->getMessage()]);
            }
        } else {
            Log::info('[ShipmentBooker] skipping direct booking — adapter has direct_api=false', ['order' => $order->order_number, 'slug' => $slug]);
        }

        // ── 3. Re-pull from storefront (async registration window) ────────────
        // Only accept a fresh consignment whose courier_slug matches the
        // configured courier — never accept a pre-existing wrong-courier shipment.
        Log::info('[ShipmentBooker] attempting fresh consignment pull', ['order' => $order->order_number, 'expected_slug' => $slug]);
        if ($fresh = $this->pullFreshConsignment($order, $slug)) {
            Log::info('[ShipmentBooker] fresh consignment accepted', ['order' => $order->order_number, 'courier_slug' => $fresh->courier_slug, 'consignment' => $fresh->consignment_id]);
            return $fresh;
        }

        // ── 4. Hard fail — order stays in Processing, no consignment created ──
        Log::warning('[ShipmentBooker] all paths exhausted — order stays in Processing', ['order' => $order->order_number, 'picked_slug' => $slug]);
        throw new CourierBookingFailedException(
            $bookingError
                ?: "Courier \"{$slug}\" did not return a consignment. The order stays in Processing — check the courier account/credentials, then retry."
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Create a synthetic consignment for stub/test mode — no real API called. */
    private function createStubConsignment(OrderMirror $order, string $slug, float $cod): CourierConsignment
    {
        Log::info('[ShipmentBooker] stub mode — synthetic consignment, no courier API called', ['order' => $order->order_number, 'slug' => $slug]);
        return CourierConsignment::create([
            'order_id'       => $order->id,
            'courier_slug'   => $slug,
            'courier_name'   => $this->name($slug),
            'consignment_id' => 'STUB-'.strtoupper($slug).'-'.$order->order_number,
            'tracking_code'  => 'STUB-'.$order->order_number,
            'tracking_url'   => null,
            'label_url'      => null,
            'cod_amount'     => $cod,
            'latest_status'  => 'booked',
            'raw_payload'    => ['source' => 'stub', 'mode' => 'stub', 'courier' => $slug],
            'booked_at'      => now(),
        ]);
    }

    /**
     * Re-pull the order from the storefront and accept a consignment only if
     * its courier_slug matches the OMS-configured courier. A pre-existing
     * wrong-courier shipment on the storefront is rejected so we never silently
     * record a different courier than what was configured.
     */
    protected function pullFreshConsignment(OrderMirror $order, string $expectedSlug): ?CourierConsignment
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
                Log::info('[ShipmentBooker] fresh consignment pull attempt failed', ['order' => $order->order_number, 'err' => $e->getMessage()]);
            }
        }

        if (! $filled) {
            try { \App\Jobs\SyncShipmentsJob::dispatchSync($order->store->id); } catch (\Throwable) {}
        }

        $fresh = $order->fresh()->consignments()->latest('id')->first();

        if (! $fresh || ! $fresh->consignment_id) return null;

        if ($fresh->courier_slug !== $expectedSlug) {
            Log::warning('[ShipmentBooker] fresh consignment courier mismatch — rejected to prevent wrong-courier fallback', [
                'order'         => $order->order_number,
                'expected_slug' => $expectedSlug,
                'found_slug'    => $fresh->courier_slug,
            ]);
            return null;
        }

        return $fresh;
    }

    private function name(string $slug): string
    {
        try { return $this->couriers->adapter($slug)->displayName(); }
        catch (\Throwable) { return ucfirst($slug); }
    }

    private function cleanCourierError(string $msg): string
    {
        $msg = trim(preg_replace('/\s+/', ' ', strip_tags($msg)));
        return mb_strimwidth($msg, 0, 180, '…');
    }

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
            Log::info('[ShipmentBooker] recording shipment on storefront failed', ['order' => $order->order_number, 'err' => $e->getMessage()]);
        }
    }

    /**
     * @return array{courier_slug:?string,courier_name:?string,consignment_id:?string,tracking_code:?string,tracking_url:?string,label_url:?string,raw:array}|null
     */
    protected function bookViaStorefront(OrderMirror $order, string $slug, float $cod): ?array
    {
        if (! $order->store) return null;

        try {
            $resp = (new StorefrontClient($order->store))->orders()->createShipment(
                $order->order_number,
                ['courier' => $slug, 'cod_amount' => $cod],
            );
        } catch (\Throwable $e) {
            Log::info('[ShipmentBooker] storefront shipment endpoint unavailable', ['order' => $order->order_number, 'err' => $e->getMessage()]);
            return null;
        }

        $d = $resp['data'] ?? $resp;

        if (empty($d['consignment_id']) && empty($d['consignmentId']) && empty($d['tracking_code'])) {
            $list = $d['shipments'] ?? $d['consignments'] ?? $resp['shipments'] ?? null;
            if (is_array($list) && $list) {
                $d = end($list);
            }
        }

        $consignment = $d['consignment_id'] ?? $d['consignmentId'] ?? null;
        $tracking    = $d['tracking_code']  ?? $d['trackingCode']  ?? $d['tracking'] ?? null;

        if (! $consignment && ! $tracking) return null;

        return [
            'courier_slug'   => $d['courier_slug'] ?? $d['courier'] ?? $slug,
            'courier_name'   => $d['courier_name'] ?? null,
            'consignment_id' => $consignment,
            'tracking_code'  => $tracking,
            'tracking_url'   => $d['tracking_url'] ?? $d['trackingUrl'] ?? null,
            'label_url'      => $d['label_url']    ?? $d['labelUrl']    ?? null,
            'raw'            => is_array($resp) ? $resp : [],
        ];
    }
}
