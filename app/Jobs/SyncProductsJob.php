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

    /** @param bool $full re-walk the entire catalogue (urgent/manual sync) instead of the incremental passes */
    public function __construct(public int $storeId, public bool $full = false) {}

    public function handle(MirrorUpserter $upserter): void
    {
        $store = Store::find($this->storeId);
        if (! $store || ! $store->is_active) return;

        try {
            $client  = new StorefrontClient($store);
            $maxSeen = $store->last_synced_products_id ?? 0;

            // ── Pass A — brand-new products, incremental by id. On a full
            //    (urgent) sync the cursor starts at 0 so EVERY product — and
            //    therefore every price — is re-pulled and upserted. ──
            $walkStart = now();
            $cursor = $this->full ? 0 : ($store->last_synced_products_id ?? 0);
            do {
                $resp  = $client->products()->list(['since_id' => $cursor, 'limit' => 100]);
                foreach (($resp['data'] ?? []) as $row) {
                    $upserter->product($store, $row);
                    if (isset($row['id']) && $row['id'] > $maxSeen) $maxSeen = $row['id'];
                }
                $cursor = $resp['paging']['next_since_id'] ?? null;
            } while ($cursor);

            // ── Full sync also reconciles DELETIONS: the walk above touched
            //    last_synced_at on every product the store still has, so any
            //    mirror it didn't touch no longer exists on the store (the
            //    storefront soft-deletes, so removed products simply vanish
            //    from the API and never appear in an incremental pass). ──
            if ($this->full) {
                $removed = $store->products()
                    ->where(fn ($q) => $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<', $walkStart))
                    ->delete();
                if ($removed > 0) {
                    Log::info('SyncProductsJob removed mirrors for store-deleted products', [
                        'store' => $store->id, 'removed' => $removed,
                    ]);
                }
            }

            // ── Pass B — products CHANGED since the last product sync (price/
            //    stock edits on EXISTING products), regardless of id. Without
            //    this a price change never crosses the id cursor and mirrors
            //    drift stale. Skipped on a full sync (Pass A covered it). ──
            if (! $this->full) {
                // Plain UTC datetime — MySQL on the storefront side compares the
                // string raw and silently matches nothing for ISO-8601 (T/offset).
                $last = $store->last_synced_products_at;
                $updatedSince = $last
                    ? \Carbon\Carbon::parse($last)->subMinutes(10)->utc()->format('Y-m-d H:i:s')
                    : now()->subDay()->utc()->format('Y-m-d H:i:s');
                $cursor = 0;
                do {
                    $resp = $client->products()->list([
                        'since_id'      => $cursor,
                        'updated_since' => $updatedSince,
                        'limit'         => 100,
                    ]);
                    foreach (($resp['data'] ?? []) as $row) {
                        $upserter->product($store, $row);
                        if (isset($row['id']) && $row['id'] > $maxSeen) $maxSeen = $row['id'];
                    }
                    $cursor = $resp['paging']['next_since_id'] ?? null;
                } while ($cursor);
            }

            $store->update([
                'last_synced_products_id' => $maxSeen,
                'last_synced_products_at' => now(),
                'last_sync_at'            => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncProductsJob failed', ['store' => $this->storeId, 'err' => $e->getMessage()]);
        }
    }
}
