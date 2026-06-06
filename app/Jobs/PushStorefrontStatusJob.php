<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pushes an OMS status change to the storefront. Dispatched via
 * dispatchAfterResponse() so the warehouse user's action returns instantly —
 * the HTTP push then runs the moment the response is flushed, in the same
 * process, with no queue worker required.
 */
class PushStorefrontStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $storeId,
        public string $orderNumber,
        public string $status,
        public ?string $note = null,
    ) {}

    public function handle(): void
    {
        $store = Store::find($this->storeId);
        if (! $store) return;

        try {
            (new StorefrontClient($store))->orders()->setStatus(
                $this->orderNumber,
                $this->status,
                $this->note,
            );
        } catch (\Throwable $e) {
            Log::warning('Storefront status push failed', [
                'order' => $this->orderNumber,
                'to'    => $this->status,
                'err'   => $e->getMessage(),
            ]);
        }
    }
}
