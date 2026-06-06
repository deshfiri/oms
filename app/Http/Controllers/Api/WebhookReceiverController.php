<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use App\Models\Store;
use App\Models\WebhookEventLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookReceiverController extends Controller
{
    public function handle(Request $request, Store $store)
    {
        if (! $store->is_active) {
            return response()->json(['ok' => false, 'error' => 'store inactive'], 403);
        }

        $signature = (string) $request->header('X-Webhook-Signature', '');
        $expected  = 'sha256='.hash_hmac('sha256', $request->getContent(), $store->webhook_secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        $eventId = (string) $request->header('X-Webhook-Event-Id', (string) Str::uuid());
        $event   = (string) $request->input('event', 'unknown');

        $existing = WebhookEventLog::where('store_id', $store->id)
            ->where('event_id', $eventId)
            ->first();

        if ($existing) {
            return response()->json(['ok' => true, 'dedup' => true]);
        }

        $log = WebhookEventLog::create([
            'store_id'    => $store->id,
            'event'       => $event,
            'event_id'    => $eventId,
            'payload'     => $request->all(),
            'received_at' => now(),
            'status'      => 'queued',
        ]);

        // Process INLINE so the order, status update, or stock change is in the
        // OMS database before the storefront's webhook request returns. Sites
        // running a queue worker can still flip this back to ::dispatch().
        try {
            ProcessWebhookJob::dispatchSync($log->id);
        } catch (\Throwable $e) {
            // Still ack the webhook so the storefront doesn't keep retrying —
            // we have the raw payload in webhook_events_log for later replay.
            \Illuminate\Support\Facades\Log::warning('Inline webhook processing failed', [
                'event' => $event, 'event_id' => $eventId, 'err' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true, 'processed' => true]);
    }
}
