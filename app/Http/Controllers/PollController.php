<?php

namespace App\Http\Controllers;

use App\Models\OrderMirror;
use App\Models\Store;
use App\Services\Orders\OrderStateMachine as S;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Lightweight change-detector for the live queues. The open page polls this
 * every few seconds; it returns a cheap signature (row count + latest change
 * timestamp) for the requested scope. The page reloads only when the signature
 * changes — so updates feel instant with no constant flicker.
 *
 * It also runs a throttled storefront pull so new orders that arrive without a
 * webhook still get picked up quickly.
 */
class PollController extends Controller
{
    /** Map a page scope to the order statuses it cares about. */
    private function statusesFor(string $scope): ?array
    {
        return match ($scope) {
            'verification' => [S::PENDING_VERIFICATION],
            'processing', 'packing' => [S::PROCESSING],
            'dispatch'     => [S::PACKED],
            'tracking'     => [S::DISPATCHED, S::SHIPPED, S::OUT_FOR_DELIVERY, S::PARTIAL_DELIVERY],
            'returns'      => [S::RETURN_PENDING, S::RETURNED, S::AWAITING_RETURN_PRODUCT],
            default        => null, // 'orders' / 'all' → everything
        };
    }

    public function signature(Request $r)
    {
        // 1) Throttled storefront pull (≈3s) so new orders AND status changes
        //    flow in near-instantly without hammering the storefront.
        $last = Cache::get('oms:autosync:last');
        if (! $last || now()->diffInSeconds(\Carbon\Carbon::parse($last)) >= 3) {
            Cache::put('oms:autosync:last', now()->toIso8601String(), 120);
            foreach (Store::where('is_active', true)->get() as $store) {
                // Orders AND courier data both come from the customer website.
                // The order list omits shipments, so also fetch details for the
                // orders still awaiting a consignment/tracking.
                try { \App\Jobs\SyncOrdersJob::dispatchSync($store->id); } catch (\Throwable) {}
                try { \App\Jobs\SyncPendingConsignmentsJob::dispatchSync($store->id); } catch (\Throwable) {}
                try { \App\Jobs\SyncShipmentsJob::dispatchSync($store->id); } catch (\Throwable) {}
            }
        }

        // Products change less often — keep the OMS catalogue (used by the order
        // & exchange pickers) in sync every ~60s so only real, registerable SKUs
        // are ever offered.
        $lastP = Cache::get('oms:autosync:products');
        if (! $lastP || now()->diffInSeconds(\Carbon\Carbon::parse($lastP)) >= 60) {
            Cache::put('oms:autosync:products', now()->toIso8601String(), 600);
            foreach (Store::where('is_active', true)->get() as $store) {
                try { \App\Jobs\SyncProductsJob::dispatchSync($store->id); } catch (\Throwable) {}
            }
        }

        // 2) Cheap local signature for the requested scope.
        $scope    = (string) $r->get('scope', 'all');
        $statuses = $this->statusesFor($scope);

        $base = OrderMirror::query();
        if ($statuses) $base->whereIn('status', $statuses);

        $count = (clone $base)->count();
        $stamp = (clone $base)->max('updated_at');

        return response()->json([
            'ok'  => true,
            'sig' => $count.'|'.($stamp ?: '0'),
        ])->header('Cache-Control', 'no-store');
    }
}
