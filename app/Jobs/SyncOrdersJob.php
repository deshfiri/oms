<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\Mirror\MirrorUpserter;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncOrdersJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $storeId) {}

    public function handle(MirrorUpserter $upserter): void
    {
        $store = Store::find($this->storeId);
        if (! $store || ! $store->is_active) return;

        try {
            $client  = new StorefrontClient($store);
            $sinceId = $store->last_synced_orders_id ?? 0;
            $maxSeen = $sinceId;

            // ── Pass A — brand-new orders, incremental by id ──
            $cursor = $sinceId;
            do {
                $resp = $client->orders()->list(['since_id' => $cursor, 'limit' => 100]);
                foreach (($resp['data'] ?? []) as $row) {
                    $upserter->order($store, $row);
                    if (isset($row['id']) && $row['id'] > $maxSeen) $maxSeen = $row['id'];
                }
                $cursor = $resp['paging']['next_since_id'] ?? null;
            } while ($cursor);

            // ── Pass B — orders CHANGED since last sync (status edits on EXISTING
            //    orders), regardless of id. This is what makes status updates flow
            //    into the OMS instead of getting stuck behind the id cursor. ──
            $last = $store->last_sync_at;
            $updatedSince = $last
                ? \Carbon\Carbon::parse($last)->subMinutes(10)->toIso8601String()
                : now()->subDay()->toIso8601String();
            $cursor = 0;
            do {
                $resp = $client->orders()->list([
                    'since_id'      => $cursor,
                    'updated_since' => $updatedSince,
                    'limit'         => 100,
                ]);
                foreach (($resp['data'] ?? []) as $row) {
                    $upserter->order($store, $row);
                    if (isset($row['id']) && $row['id'] > $maxSeen) $maxSeen = $row['id'];
                }
                $cursor = $resp['paging']['next_since_id'] ?? null;
            } while ($cursor);

            $store->update([
                'last_synced_orders_id' => $maxSeen,
                'last_sync_at'          => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncOrdersJob failed', ['store' => $this->storeId, 'err' => $e->getMessage()]);
        }
    }
}
