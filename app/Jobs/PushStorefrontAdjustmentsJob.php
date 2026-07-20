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
 * Pushes an OMS order-level money change (discount, shipping charge) to the
 * storefront so the store's copy of the order — and its COD amount — matches
 * what CS set during verification. Dispatched via dispatchAfterResponse() so the
 * agent's action returns instantly; the HTTP push then runs the moment the
 * response is flushed, in the same process, with no queue worker required.
 *
 * Only order-level adjustments travel here — never item/product prices. The
 * store re-derives its grand total from its own (unchanged) line items.
 */
class PushStorefrontAdjustmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array{discount?:float, shipping_total?:float} $adjustments */
    public function __construct(
        public int $storeId,
        public string $orderNumber,
        public array $adjustments,
    ) {}

    public function handle(): void
    {
        $store = Store::find($this->storeId);
        if (! $store) return;

        try {
            (new StorefrontClient($store))->orders()->updateAdjustments(
                $this->orderNumber,
                $this->adjustments,
            );
        } catch (\Throwable $e) {
            Log::warning('Storefront adjustments push failed', [
                'order'       => $this->orderNumber,
                'adjustments' => $this->adjustments,
                'err'         => $e->getMessage(),
            ]);
        }
    }
}
