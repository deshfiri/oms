<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\Mirror\MirrorUpserter;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncProductsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $storeId) {}

    public function handle(MirrorUpserter $upserter): void
    {
        $store = Store::find($this->storeId);
        if (! $store || ! $store->is_active) return;

        try {
            $client  = new StorefrontClient($store);
            $sinceId = $store->last_synced_products_id ?? 0;
            $maxSeen = $sinceId;
            $cursor  = $sinceId;

            do {
                $resp = $client->products()->list(['since_id' => $cursor, 'limit' => 100]);
                $items = $resp['data'] ?? [];
                foreach ($items as $row) {
                    $upserter->product($store, $row);
                    if (isset($row['id']) && $row['id'] > $maxSeen) $maxSeen = $row['id'];
                }
                $cursor = $resp['paging']['next_since_id'] ?? null;
            } while ($cursor);

            $store->update(['last_synced_products_id' => $maxSeen, 'last_sync_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('SyncProductsJob failed', ['store' => $this->storeId, 'err' => $e->getMessage()]);
        }
    }
}
