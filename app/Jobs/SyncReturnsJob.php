<?php

namespace App\Jobs;

use App\Models\OrderMirror;
use App\Models\RmaWorkflow;
use App\Models\Store;
use App\Services\Storefront\StorefrontClient;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncReturnsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $storeId) {}

    public function handle(): void
    {
        $store = Store::find($this->storeId);
        if (! $store || ! $store->is_active) return;

        try {
            $client = new StorefrontClient($store);
            $cursor = 0;
            do {
                $resp = $client->returns()->list(['since_id' => $cursor, 'limit' => 100]);
                foreach (($resp['data'] ?? []) as $row) {
                    $order = OrderMirror::where('store_id', $store->id)
                        ->where('order_number', $row['order_number'] ?? '')
                        ->first();
                    if (! $order) continue;

                    RmaWorkflow::updateOrCreate(
                        ['store_id' => $store->id, 'return_id_remote' => $row['id']],
                        [
                            'order_id'              => $order->id,
                            'status'                => $row['status'] ?? 'requested',
                            'inbound_tracking_code' => $row['inbound_tracking_code'] ?? null,
                            'opened_at'             => isset($row['requested_at']) ? Carbon::parse($row['requested_at']) : null,
                            'approved_at'           => isset($row['approved_at']) ? Carbon::parse($row['approved_at']) : null,
                            'received_at'           => isset($row['received_at']) ? Carbon::parse($row['received_at']) : null,
                            'completed_at'          => isset($row['completed_at']) ? Carbon::parse($row['completed_at']) : null,
                        ],
                    );
                }
                $cursor = $resp['paging']['next_since_id'] ?? null;
            } while ($cursor);
        } catch (\Throwable $e) {
            Log::error('SyncReturnsJob failed', ['store' => $this->storeId, 'err' => $e->getMessage()]);
        }
    }
}
