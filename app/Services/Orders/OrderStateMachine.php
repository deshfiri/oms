<?php

namespace App\Services\Orders;

use App\Models\OrderMirror;
use Illuminate\Validation\ValidationException;

/**
 * Amazon/Daraz-style order lifecycle per docs/newf.md.
 *
 *   pending_verification → confirmed | cancelled
 *   confirmed            → processing | cancelled
 *   processing           → packed | cancelled
 *   packed               → dispatched | cancelled
 *   dispatched           → shipped | return_pending
 *   shipped              → out_for_delivery | return_pending
 *   out_for_delivery     → delivered | return_pending | lost
 *   delivered            → exchange_requested | return_pending
 *   return_pending       → returned | lost
 *   returned             → restockable | damaged
 *   restockable          → (terminal — restocked into available)
 *   damaged              → (terminal — written off)
 *   exchange_requested   → exchange_processing | cancelled
 *   exchange_processing  → awaiting_return_product
 *   awaiting_return_product → returned | lost
 *   lost                 → (terminal — compensated)
 *   cancelled            → (terminal)
 */
class OrderStateMachine
{
    public const PENDING_VERIFICATION    = 'pending_verification';
    public const CONFIRMED               = 'confirmed';
    public const CANCELLED               = 'cancelled';
    public const PROCESSING              = 'processing';
    public const PACKED                  = 'packed';
    public const DISPATCHED              = 'dispatched';
    public const SHIPPED                 = 'shipped';
    public const OUT_FOR_DELIVERY        = 'out_for_delivery';
    public const DELIVERED               = 'delivered';
    public const PARTIAL_DELIVERY        = 'partial_delivery';
    public const RETURN_PENDING          = 'return_pending';
    public const RETURNED                = 'returned';
    public const RESTOCKABLE             = 'restockable';
    public const DAMAGED                 = 'damaged';
    public const EXCHANGE_REQUESTED      = 'exchange_requested';
    public const EXCHANGE_PROCESSING     = 'exchange_processing';
    public const AWAITING_RETURN_PRODUCT = 'awaiting_return_product';
    public const LOST                    = 'lost';

    /** Order lifecycle statuses (per the OMS workflow spec). */
    public const STATUSES = [
        self::PENDING_VERIFICATION, self::CONFIRMED, self::CANCELLED,
        self::PROCESSING, self::PACKED, self::DISPATCHED,
        self::SHIPPED, self::OUT_FOR_DELIVERY, self::DELIVERED, self::PARTIAL_DELIVERY,
        self::RETURN_PENDING, self::RETURNED, self::RESTOCKABLE, self::DAMAGED,
        self::EXCHANGE_REQUESTED, self::EXCHANGE_PROCESSING,
        self::AWAITING_RETURN_PRODUCT, self::LOST,
    ];

    public const LABELS = [
        self::PENDING_VERIFICATION => 'Pending Verification',
        self::CONFIRMED            => 'Confirmed',
        self::CANCELLED            => 'Cancelled',
        self::PROCESSING           => 'Processing',
        self::PACKED               => 'Packed',
        self::DISPATCHED           => 'Dispatched',
        self::SHIPPED              => 'Shipped',
        self::OUT_FOR_DELIVERY     => 'Out For Delivery',
        self::DELIVERED            => 'Delivered',
        self::PARTIAL_DELIVERY     => 'Partial Delivery',
        self::RETURN_PENDING       => 'Waiting for Return',
        self::RETURNED             => 'Returned',
        self::RESTOCKABLE          => 'Restockable',
        self::DAMAGED              => 'Damaged',
        self::EXCHANGE_REQUESTED   => 'Exchange Requested',
        self::EXCHANGE_PROCESSING  => 'Exchange Processing',
        self::AWAITING_RETURN_PRODUCT => 'Awaiting Return Product',
        self::LOST                 => 'Lost',
    ];

    public const FORWARD = [
        self::PENDING_VERIFICATION => [self::CONFIRMED, self::CANCELLED],
        self::CONFIRMED            => [self::PROCESSING, self::CANCELLED],
        self::PROCESSING           => [self::PACKED, self::CANCELLED],
        // Lost can happen anywhere the parcel left our hands.
        self::PACKED               => [self::DISPATCHED, self::CANCELLED, self::LOST],
        self::DISPATCHED           => [self::SHIPPED, self::RETURN_PENDING, self::LOST],
        self::SHIPPED              => [self::OUT_FOR_DELIVERY, self::DELIVERED, self::PARTIAL_DELIVERY, self::RETURN_PENDING, self::LOST],
        self::OUT_FOR_DELIVERY     => [self::DELIVERED, self::PARTIAL_DELIVERY, self::RETURN_PENDING, self::LOST],
        // Partial delivery: some items delivered, the rest either gets delivered
        // later or comes back as a return.
        self::PARTIAL_DELIVERY     => [self::DELIVERED, self::RETURN_PENDING, self::EXCHANGE_REQUESTED],
        self::DELIVERED            => [self::EXCHANGE_REQUESTED, self::RETURN_PENDING],
        self::RETURN_PENDING       => [self::RETURNED, self::LOST],
        self::RETURNED             => [self::RESTOCKABLE, self::DAMAGED],
        self::EXCHANGE_REQUESTED   => [self::EXCHANGE_PROCESSING, self::AWAITING_RETURN_PRODUCT, self::CANCELLED],
        self::EXCHANGE_PROCESSING  => [self::AWAITING_RETURN_PRODUCT],
        self::AWAITING_RETURN_PRODUCT => [self::RETURNED, self::LOST],
        self::CANCELLED            => [],
        self::RESTOCKABLE          => [],
        self::DAMAGED              => [],
        self::LOST                 => [],
    ];

    /** Stages that pickers/packers should see in the warehouse queue. */
    public const WAREHOUSE_STAGES = [
        self::PROCESSING, self::PACKED, self::DISPATCHED,
    ];

    public function allowedNext(string $from): array
    {
        return self::FORWARD[$from] ?? [];
    }

    /**
     * Shortest forward path of statuses to get from $from to $to (excluding
     * $from itself), or [] if unreachable. Used to "catch up" the OMS when the
     * courier reports a status several steps ahead.
     */
    public function pathTo(string $from, string $to): array
    {
        if ($from === $to) return [];
        $queue   = [[$from]];
        $visited = [$from => true];
        while ($queue) {
            $path = array_shift($queue);
            $last = end($path);
            foreach (self::FORWARD[$last] ?? [] as $next) {
                if (isset($visited[$next])) continue;
                $newPath = array_merge($path, [$next]);
                if ($next === $to) return array_slice($newPath, 1);
                $visited[$next] = true;
                $queue[] = $newPath;
            }
        }
        return [];
    }

    /**
     * Drive the order toward a courier-reported status, walking through any
     * required intermediate states so a real-world update is never dropped.
     * (e.g. courier says "delivered" while the OMS is at "dispatched" → the
     * order is advanced dispatched → shipped → out_for_delivery → delivered.)
     */
    public function drive(OrderMirror $order, string $to, array $meta = []): OrderMirror
    {
        foreach ($this->pathTo($order->status, $to) as $step) {
            try { $order = $this->transition($order, $step, $meta); }
            catch (\Throwable) { break; }
        }
        return $order;
    }

    public function canTransition(string $from, string $to): bool
    {
        if ($from === $to) return true;
        return in_array($to, self::FORWARD[$from] ?? [], true);
    }

    public function label(string $status): string
    {
        return self::LABELS[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    /**
     * Translate a normalized courier status (picked_up, in_transit, hub_received,
     * out_for_delivery, delivered, partial_delivered, delivery_failed,
     * return_initiated, returned, lost) into an OMS lifecycle target, or null
     * when it implies no lifecycle move. Single source of truth shared by the
     * courier webhook and the storefront shipment sync.
     */
    public static function courierStatusToLifecycle(string $norm): ?string
    {
        return match ($norm) {
            'picked_up', 'in_transit', 'hub_received' => self::SHIPPED,
            'out_for_delivery'  => self::OUT_FOR_DELIVERY,
            'delivered'         => self::DELIVERED,
            'partial_delivered' => self::PARTIAL_DELIVERY,
            'delivery_failed', 'return_initiated' => self::RETURN_PENDING,
            'returned'          => self::RETURNED,
            'lost'              => self::LOST,
            default             => null,
        };
    }

    /**
     * Translate an OMS lifecycle status into the storefront's status vocabulary
     * (pending, confirmed, processing, packed, shipped, out_for_delivery,
     * delivered, cancelled, returned). Returns null for OMS-only statuses that
     * must NOT be pushed.
     *
     * The storefront has no "dispatched" state — handing a parcel to the courier
     * IS "shipped" from the customer's point of view, so dispatched → shipped.
     * This is what fixes the storefront getting stuck on "packed".
     */
    public static function storefrontStatus(string $oms): ?string
    {
        return match ($oms) {
            self::CONFIRMED        => 'confirmed',
            self::PROCESSING       => 'processing',
            self::PACKED           => 'packed',
            self::DISPATCHED       => 'shipped',          // handed to courier ⇒ shipped
            self::SHIPPED          => 'shipped',
            self::OUT_FOR_DELIVERY => 'out_for_delivery',
            self::DELIVERED        => 'delivered',
            self::PARTIAL_DELIVERY => 'delivered',
            self::CANCELLED        => 'cancelled',
            self::RETURNED         => 'returned',
            // OMS-only / handled via dedicated endpoints — never push as a status:
            default                => null,
        };
    }

    /**
     * Push the transition through to the storefront and adjust local state +
     * inventory + audit. `meta` carries side-effect payloads (cancel_reason,
     * return_reason, lost_party, damaged_qty, etc.).
     */
    public function transition(OrderMirror $order, string $to, array $meta = []): OrderMirror
    {
        $from = $order->status;
        if (! $this->canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    "Order cannot move from '%s' to '%s'. Allowed: %s",
                    $this->label($from), $this->label($to),
                    implode(', ', array_map(fn ($s) => $this->label($s), $this->allowedNext($from))) ?: '(none)',
                ),
            ]);
        }

        if ($from === $to) return $order;

        // Push to storefront, translating the OMS lifecycle into the storefront's
        // own status vocabulary. OMS-only statuses (restockable/damaged/lost/
        // exchange_*) and return_pending map to null and are kept local — the
        // storefront learns about those via dedicated endpoints.
        $sfStatus = self::storefrontStatus($to);
        if ($sfStatus && $order->store) {
            // Fire the push the instant the response flushes — the warehouse
            // action returns immediately, the storefront updates a fraction of
            // a second later, no queue worker needed.
            \App\Jobs\PushStorefrontStatusJob::dispatchAfterResponse(
                $order->store->id,
                $order->order_number,
                $sfStatus,
                $meta['note'] ?? null,
            );
        }

        $updates = ['status' => $to];
        // Side-effect timestamps so reports can read cleanly.
        $now = now();
        if ($to === self::CONFIRMED)        $updates['confirmed_at']        = $now;
        if ($to === self::PROCESSING)       $updates['processing_at']       = $now;
        if ($to === self::PACKED)           $updates['packed_at']           = $now;
        if ($to === self::DISPATCHED)       $updates['dispatched_at']       = $now;
        if ($to === self::SHIPPED)          $updates['shipped_at']          = $now;
        if ($to === self::OUT_FOR_DELIVERY) $updates['out_for_delivery_at'] = $now;
        if ($to === self::DELIVERED)        $updates['delivered_at']        = $now;
        if ($to === self::RETURN_PENDING)   $updates['return_pending_at']   = $now;
        if ($to === self::RETURNED)         $updates['returned_at']         = $now;
        if ($to === self::CANCELLED) {
            $updates['cancelled_at']    = $now;
            $updates['cancel_reason']   = $meta['reason'] ?? null;
        }
        if ($to === self::RETURN_PENDING && isset($meta['reason'])) {
            $updates['return_reason']   = $meta['reason'];
        }

        $order->update($updates);

        // Audit
        \App\Models\AuditLog::create([
            'user_id'     => auth()->id(),
            'action'      => 'order.status.'.$to,
            'entity_type' => OrderMirror::class,
            'entity_id'   => $order->id,
            'before_json' => ['status' => $from],
            'after_json'  => ['status' => $to] + $meta,
            'ip'          => request()?->ip(),
            'user_agent'  => request()?->userAgent(),
        ]);

        // Inventory side-effects
        try {
            app(\App\Services\Inventory\InventoryEngine::class)->onTransition($order->refresh(), $from, $to, $meta);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Inventory adjust failed', ['err' => $e->getMessage()]);
        }

        return $order->refresh();
    }
}
