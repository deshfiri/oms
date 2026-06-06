<?php

namespace App\Services\Mirror;

use App\Models\CourierConsignment;
use App\Models\CourierTrackingEvent;
use App\Models\CustomerMirror;
use App\Models\OrderItemMirror;
use App\Models\OrderMirror;
use App\Models\ProductMirror;
use App\Models\ShipmentLog;
use App\Models\Store;
use App\Services\Couriers\CourierManager;
use App\Services\Orders\OrderStateMachine;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class MirrorUpserter
{
    public function order(Store $store, array $data): OrderMirror
    {
        // DFCOMMERCE puts money under `totals`; webhooks may send flat. Support both.
        $totals = $data['totals'] ?? [];
        $subtotal       = $totals['subtotal']  ?? $data['subtotal']       ?? 0;
        $discount       = $totals['discount']  ?? $data['discount']       ?? 0;
        $shippingTotal  = $totals['shipping']  ?? $data['shipping_total'] ?? 0;
        $tax            = $totals['tax']       ?? $data['tax']            ?? 0;
        $grand          = $totals['grand']     ?? $data['grand_total']    ?? 0;
        $paid           = $totals['paid']      ?? $data['paid_total']     ?? 0;

        // Map legacy storefront statuses into the new 17-state lifecycle.
        $mapped = $this->mapStatus($data['status'] ?? 'pending');

        $order = OrderMirror::updateOrCreate(
            ['store_id' => $store->id, 'order_number' => $data['order_number']],
            [
                'status'              => $mapped,
                'payment_status'      => $data['payment_status'] ?? null,
                'payment_method'      => $data['payment_method'] ?? null,
                'currency'            => $data['currency'] ?? 'BDT',
                'customer_name'       => Arr::get($data, 'customer.name'),
                'customer_email'      => Arr::get($data, 'customer.email'),
                'customer_phone'      => Arr::get($data, 'customer.phone'),
                'shipping_name'       => Arr::get($data, 'shipping.name'),
                'shipping_phone'      => Arr::get($data, 'shipping.phone'),
                'shipping_address_line' => Arr::get($data, 'shipping.address')
                                       ?? Arr::get($data, 'shipping.address_line'),
                'shipping_area'       => Arr::get($data, 'shipping.area'),
                'shipping_city'       => Arr::get($data, 'shipping.city'),
                'shipping_district'   => Arr::get($data, 'shipping.district'),
                'shipping_zone'       => Arr::get($data, 'shipping.zone'),
                // Courier chosen on the store website, if the storefront sends it.
                'preferred_courier'   => Arr::get($data, 'shipping.courier')
                                       ?? Arr::get($data, 'courier')
                                       ?? Arr::get($data, 'shipping.courier_slug')
                                       ?? Arr::get($data, 'shipping_method'),
                'subtotal'            => $subtotal,
                'discount'            => $discount,
                'shipping_total'      => $shippingTotal,
                'tax'                 => $tax,
                'grand_total'         => $grand,
                'paid_total'          => $paid,
                'placed_at'           => isset($data['placed_at']) ? Carbon::parse($data['placed_at']) : null,
                'updated_at_remote'   => isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : null,
                'last_synced_at'      => now(),
                'raw_payload'         => $data,
            ],
        );

        if (! empty($data['items']) && is_array($data['items'])) {
            $order->items()->delete();
            foreach ($data['items'] as $item) {
                OrderItemMirror::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item['product_id'] ?? null,
                    'sku'           => $item['sku'] ?? null,
                    'name'          => $item['name'] ?? 'Item',
                    'variant_label' => $item['variant'] ?? $item['variant_label'] ?? null,
                    'qty'           => $item['qty'] ?? 1,
                    'unit_price'    => $item['unit_price'] ?? 0,
                    'line_total'    => $item['line_total'] ?? 0,
                ]);
            }
        }

        // The storefront embeds courier data in the order's `shipments[]` array
        // (consignment_id, tracking_code, courier, status). Fold each into the
        // canonical courier_consignments so the real consignment shows in the
        // OMS the instant the order syncs — this is how "Send to courier" data
        // reaches Packing/Dispatch.
        $this->applyShipments($order, $data);

        return $order;
    }

    /**
     * Extract any courier/shipment rows from a storefront response (whatever the
     * shape) and fold them into this order's consignments. Returns true if a
     * real consignment id landed. Shape-tolerant: handles the order object, a
     * `{data: …}` envelope, a list of orders, an embedded `shipments[]` /
     * `consignments[]`, or a single shipment object.
     */
    public function applyShipments(OrderMirror $order, array $resp): bool
    {
        $filled = false;
        foreach ($this->extractShipmentRows($resp, $order->order_number) as $row) {
            // Never create an empty consignment — only real courier data lands.
            $cid   = $row['consignment_id'] ?? $row['consignmentId'] ?? null;
            $track = $row['tracking_code'] ?? $row['trackingCode'] ?? $row['tracking'] ?? null;
            if (! $cid && ! $track) continue;
            try {
                $cons = $this->upsertConsignment($order, $row);
                if ($cons->consignment_id) $filled = true;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('applyShipments upsert failed', ['err' => $e->getMessage()]);
            }
        }
        return $filled;
    }

    /** @return array<int,array> shipment rows found anywhere in $resp */
    protected function extractShipmentRows(array $resp, ?string $orderNumber): array
    {
        $node = $resp['data'] ?? $resp;

        // A list of orders → pick the matching one (or the first).
        if (array_is_list($node)) {
            $match = null;
            foreach ($node as $o) {
                if (! is_array($o)) continue;
                if (($o['order_number'] ?? null) == $orderNumber) { $match = $o; break; }
                $match = $match ?? $o;
            }
            $node = $match ?? [];
        }

        $rows = [];
        foreach (['shipments', 'consignments'] as $k) {
            if (! empty($node[$k]) && is_array($node[$k])) {
                foreach ($node[$k] as $s) if (is_array($s)) $rows[] = $s;
            }
        }
        // Single shipment object (consignment fields at the node root).
        if (! $rows && (isset($node['consignment_id']) || isset($node['consignmentId']) || isset($node['tracking_code']))) {
            $rows[] = $node;
        }
        return $rows;
    }

    public function product(Store $store, array $data): ProductMirror
    {
        // DFCOMMERCE nests stock under `stock`; webhooks may flatten. Support both.
        $stock = $data['stock'] ?? [];

        return ProductMirror::updateOrCreate(
            ['store_id' => $store->id, 'sku' => $data['sku']],
            [
                'product_id'          => $data['id'] ?? $data['product_id'] ?? 0,
                'name'                => $data['name'] ?? $data['sku'],
                'type'                => $data['type'] ?? 'simple',
                'price'               => $data['price'] ?? 0,
                'sale_price'          => $data['sale_price'] ?? null,
                'stock_quantity'      => $stock['quantity']     ?? $data['stock_quantity']      ?? 0,
                'low_stock_threshold' => $stock['low_threshold']?? $data['low_stock_threshold'] ?? 5,
                'stock_status'        => $stock['status']       ?? $data['stock_status']        ?? 'in_stock',
                'manage_stock'        => $stock['managed']      ?? $data['manage_stock']        ?? true,
                'bin_location'        => $data['bin_location'] ?? null,
                'image_url'           => $data['image_url'] ?? Arr::get($data, 'images.0.url'),
                'updated_at_remote'   => isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : null,
                'last_synced_at'      => now(),
            ],
        );
    }

    public function customer(Store $store, array $data): CustomerMirror
    {
        return CustomerMirror::updateOrCreate(
            ['store_id' => $store->id, 'customer_id' => $data['id'] ?? $data['customer_id']],
            [
                'name'           => $data['name'] ?? null,
                'email'          => $data['email'] ?? null,
                'phone'          => $data['phone'] ?? null,
                'last_synced_at' => now(),
            ],
        );
    }

    /** Map storefront status names into the new OMS lifecycle. */
    protected function mapStatus(string $raw): string
    {
        return match (strtolower($raw)) {
            'pending'           => \App\Services\Orders\OrderStateMachine::PENDING_VERIFICATION,
            'confirmed'         => \App\Services\Orders\OrderStateMachine::CONFIRMED,
            'processing'        => \App\Services\Orders\OrderStateMachine::PROCESSING,
            'packed'            => \App\Services\Orders\OrderStateMachine::PACKED,
            'shipped'           => \App\Services\Orders\OrderStateMachine::SHIPPED,
            'out_for_delivery'  => \App\Services\Orders\OrderStateMachine::OUT_FOR_DELIVERY,
            'delivered'         => \App\Services\Orders\OrderStateMachine::DELIVERED,
            'cancelled'         => \App\Services\Orders\OrderStateMachine::CANCELLED,
            'returned'          => \App\Services\Orders\OrderStateMachine::RETURNED,
            'refunded'          => \App\Services\Orders\OrderStateMachine::CANCELLED,
            default             => $raw,
        };
    }

    public function shipment(Store $store, array $data): CourierConsignment
    {
        $order = OrderMirror::where('store_id', $store->id)
            ->where('order_number', $data['order_number'] ?? '')
            ->first();
        if (! $order) {
            throw new \RuntimeException('Cannot mirror shipment: unknown order '.($data['order_number'] ?? '?'));
        }

        // Audit log (legacy table) — keep for history.
        ShipmentLog::updateOrCreate(
            ['store_id' => $store->id, 'remote_shipment_id' => $data['id'] ?? null],
            [
                'order_id'        => $order->id,
                'courier'         => $data['courier'] ?? 'unknown',
                'tracking_code'   => $data['tracking_code'] ?? null,
                'consignment_id'  => $data['consignment_id'] ?? null,
                'status'          => $data['status'] ?? 'booked',
                'cod_amount'      => $data['cod_amount'] ?? 0,
                'booked_at'       => isset($data['booked_at']) ? Carbon::parse($data['booked_at']) : null,
                'picked_up_at'    => isset($data['picked_up_at']) ? Carbon::parse($data['picked_up_at']) : null,
                'delivered_at'    => isset($data['delivered_at']) ? Carbon::parse($data['delivered_at']) : null,
                'last_status_at'  => now(),
                'pod_url'         => $data['pod_url'] ?? null,
            ],
        );

        // Canonical: fold the website's courier data into courier_consignments —
        // the table every OMS screen reads. This is how Pathao/CarryBee/RedX/
        // Steadfast (whatever the website chose) get their REAL consignment id +
        // tracking into the OMS. Works uniformly for all couriers.
        return $this->upsertConsignment($order, $data);
    }

    /**
     * Upsert the courier consignment from a storefront shipment payload, record
     * tracking events, and advance the order lifecycle from the courier status.
     */
    protected function upsertConsignment(OrderMirror $order, array $data): CourierConsignment
    {
        $slug  = $data['courier_slug'] ?? $data['courier'] ?? 'manual';
        $cid   = $data['consignment_id'] ?? $data['consignmentId'] ?? null;
        $track = $data['tracking_code'] ?? $data['trackingCode'] ?? $data['tracking'] ?? null;
        $url   = $data['tracking_url'] ?? $data['trackingUrl'] ?? null;
        $label = $data['label_url'] ?? $data['labelUrl'] ?? null;
        $native = (string) ($data['status'] ?? 'booked');

        // Find the consignment to update. The unique key is (courier_slug,
        // consignment_id), so a real id must be matched GLOBALLY (not just within
        // this order) to update-in-place rather than collide. Then fall back to
        // this order's pending placeholder, else create a fresh row.
        $cons = null;
        if ($cid)   $cons = CourierConsignment::where('courier_slug', $slug)->where('consignment_id', $cid)->first();
        if (! $cons && $track) $cons = $order->consignments()->where('tracking_code', $track)->first();
        if (! $cons) $cons = $order->consignments()->whereNull('consignment_id')->latest('id')->first();
        // Replace an OMS-generated PLACEHOLDER with the real consignment.
        if (! $cons) $cons = $order->consignments()->where('latest_status', 'placeholder')->latest('id')->first();
        if (! $cons) $cons = new CourierConsignment(['order_id' => $order->id]);

        // Normalize the courier status through the matching adapter.
        try { $norm = app(CourierManager::class)->adapter($slug)->normalizeStatus($native); }
        catch (\Throwable) { $norm = strtolower($native); }

        $cons->fill([
            'order_id'       => $order->id,
            'courier_slug'   => $slug,
            'courier_name'   => $data['courier_name'] ?? ($cons->courier_name ?: ucfirst($slug)),
            'consignment_id' => $cid ?: $cons->consignment_id,
            'tracking_code'  => $track ?: $cons->tracking_code,
            'tracking_url'   => $url ?: $cons->tracking_url,
            'label_url'      => $label ?: $cons->label_url,
            'cod_amount'     => $data['cod_amount'] ?? $cons->cod_amount ?? 0,
            'latest_status'  => $norm,
            'booked_at'      => $cons->booked_at ?? (isset($data['booked_at']) ? Carbon::parse($data['booked_at']) : now()),
            'raw_payload'    => ($cons->raw_payload ?? []) + ['last_shipment_sync' => $data, 'source' => 'storefront_api'],
        ])->save();

        // Tracking timeline — record explicit events if provided, else the
        // current status. Deduped on (consignment, status, happened_at).
        $events = $data['events'] ?? $data['tracking_events'] ?? null;
        if (is_array($events) && $events) {
            foreach ($events as $ev) {
                $evNative = (string) ($ev['status'] ?? $ev['event'] ?? 'unknown');
                try { $evNorm = app(CourierManager::class)->adapter($slug)->normalizeStatus($evNative); }
                catch (\Throwable) { $evNorm = strtolower($evNative); }
                $when = isset($ev['happened_at']) ? Carbon::parse($ev['happened_at']) : now();
                CourierTrackingEvent::firstOrCreate(
                    ['consignment_id' => $cons->id, 'status' => $evNorm, 'happened_at' => $when],
                    ['location' => $ev['location'] ?? null, 'remark' => $ev['remark'] ?? null, 'raw_payload' => $ev],
                );
            }
        } else {
            $when = isset($data['last_status_at']) ? Carbon::parse($data['last_status_at']) : now();
            CourierTrackingEvent::firstOrCreate(
                ['consignment_id' => $cons->id, 'status' => $norm, 'happened_at' => $when],
                ['location' => $data['location'] ?? null, 'remark' => $data['remark'] ?? null, 'raw_payload' => $data],
            );
        }

        // Advance the order lifecycle from the courier status (shipped, OFD,
        // delivered, returned, lost). Illegal jumps are ignored.
        $target = OrderStateMachine::courierStatusToLifecycle($norm);
        if ($target) {
            try {
                $order = app(OrderStateMachine::class)->drive($order, $target, [
                    'note'   => "Courier:$slug → $native (website sync)",
                    'reason' => $norm === 'delivery_failed' ? 'delivery_failure' : null,
                ]);
            } catch (\Throwable) {
                // Not a legal transition right now — leave the order as-is.
            }
        }

        // Bump the order so the instant page-poller notices the new courier data
        // (consignment id / tracking) even when the lifecycle didn't change.
        $order->touch();

        return $cons->refresh();
    }
}
