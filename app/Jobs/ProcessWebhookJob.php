<?php

namespace App\Jobs;

use App\Models\OrderMirror;
use App\Models\RmaWorkflow;
use App\Models\WebhookEventLog;
use App\Services\Mirror\MirrorUpserter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $backoff = 30;

    public function __construct(public int $eventLogId) {}

    public function handle(MirrorUpserter $upserter): void
    {
        $log = WebhookEventLog::find($this->eventLogId);
        if (! $log) {
            return;
        }

        try {
            $store   = $log->store;
            $payload = $log->payload['data'] ?? $log->payload;
            $event   = $log->event;

            switch ($event) {
                case 'order.placed':
                case 'order.updated':
                case 'order.cancelled':
                case 'order.refunded':
                    $upserter->order($store, $payload);
                    break;

                case 'payment.received':
                    if (! empty($payload['order_number'])) {
                        OrderMirror::where('store_id', $store->id)
                            ->where('order_number', $payload['order_number'])
                            ->update(['paid_total' => $payload['paid_total'] ?? 0, 'payment_status' => 'paid']);
                    }
                    break;

                case 'shipment.booked':
                case 'shipment.delivered':
                    $upserter->shipment($store, $payload);
                    if ($event === 'shipment.delivered' && ! empty($payload['order_number'])) {
                        OrderMirror::where('store_id', $store->id)
                            ->where('order_number', $payload['order_number'])
                            ->update(['status' => 'delivered']);
                    }
                    break;

                case 'return.requested':
                case 'return.approved':
                case 'return.completed':
                    $order = OrderMirror::where('store_id', $store->id)
                        ->where('order_number', $payload['order_number'] ?? '')
                        ->first();
                    if ($order) {
                        RmaWorkflow::updateOrCreate(
                            ['store_id' => $store->id, 'return_id_remote' => $payload['id'] ?? null],
                            [
                                'order_id'              => $order->id,
                                'status'                => str_replace('return.', '', $event),
                                'inbound_tracking_code' => $payload['inbound_tracking_code'] ?? null,
                                'opened_at'             => $payload['opened_at'] ?? now(),
                            ],
                        );
                    }
                    break;

                case 'damage.recorded':
                    // optional: keep store-side record only; OMS logs its own damages
                    break;

                case 'inventory.low_stock':
                    if (! empty($payload['sku'])) {
                        $upserter->product($store, $payload);
                    }
                    break;

                default:
                    Log::info('Unhandled webhook event', ['event' => $event, 'store' => $store->id]);
            }

            $log->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
