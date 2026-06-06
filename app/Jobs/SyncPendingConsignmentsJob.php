<?php

namespace App\Jobs;

use App\Models\OrderMirror;
use App\Models\Store;
use App\Services\Mirror\MirrorUpserter;
use App\Services\Orders\OrderStateMachine as S;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * The storefront's order *list* endpoint omits courier data, but the order
 * *detail* endpoint embeds `shipments[]` (consignment id, tracking, status). So
 * for the handful of orders still awaiting courier data, fetch their detail and
 * fold the shipments in — this is what makes the real consignment + tracking
 * appear automatically (no clicking) after "Send to courier".
 *
 * Bounded: only non-terminal post-processing orders that still need data, newest
 * first, capped — so it's cheap to run on the live poll.
 */
class SyncPendingConsignmentsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $storeId, public int $cap = 25) {}

    public function handle(MirrorUpserter $upserter): void
    {
        $store = Store::find($this->storeId);
        if (! $store || ! $store->is_active) return;

        // Courier registration only ever happens via "Send to courier" in
        // Processing — the poller never creates consignments. It only refreshes
        // TRACKING for parcels that are already in transit and already have a
        // consignment.
        $orders = OrderMirror::where('store_id', $store->id)
            ->whereIn('status', [S::DISPATCHED, S::SHIPPED, S::OUT_FOR_DELIVERY])
            ->whereHas('consignments', fn ($x) => $x->whereNotNull('consignment_id'))
            ->latest('updated_at')
            ->limit($this->cap)
            ->get();

        if ($orders->isEmpty()) return;

        $client = new StorefrontClient($store);
        foreach ($orders as $order) {
            try {
                $resp = $client->orders()->find($order->order_number);
                if (is_array($resp)) $upserter->applyShipments($order, $resp);
            } catch (\Throwable $e) {
                Log::info('SyncPendingConsignments detail fetch failed', [
                    'order' => $order->order_number, 'err' => $e->getMessage(),
                ]);
            }
        }
    }
}
