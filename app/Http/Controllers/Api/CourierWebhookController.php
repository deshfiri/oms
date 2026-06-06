<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourierConsignment;
use App\Models\CourierTrackingEvent;
use App\Services\Couriers\CourierManager;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;

class CourierWebhookController extends Controller
{
    public function handle(Request $r, string $slug, CourierManager $couriers, OrderStateMachine $sm)
    {
        $adapter = $couriers->adapter($slug);

        // Identify the consignment we're updating.
        $code = $r->input('tracking_code') ?? $r->input('consignment_id');
        $cons = CourierConsignment::where('courier_slug', $slug)
            ->where(function ($q) use ($code) {
                $q->where('tracking_code', $code)->orWhere('consignment_id', $code);
            })->first();

        if (! $cons) {
            return response()->json(['ok' => false, 'error' => 'consignment not found'], 404);
        }

        $native = (string) ($r->input('status') ?? $r->input('event') ?? 'unknown');
        $norm   = $adapter->normalizeStatus($native);

        CourierTrackingEvent::create([
            'consignment_id' => $cons->id,
            'status'         => $norm,
            'location'       => $r->input('location'),
            'remark'         => $r->input('remark'),
            'raw_payload'    => $r->all(),
            'happened_at'    => $r->input('happened_at') ? \Carbon\Carbon::parse($r->input('happened_at')) : now(),
        ]);
        $cons->update(['latest_status' => $norm]);

        // Bubble up to the order's lifecycle (shared map — same as website sync).
        $target = OrderStateMachine::courierStatusToLifecycle($norm);
        if ($target && $cons->order) {
            try {
                $sm->drive($cons->order, $target, [
                    'note'   => "Courier:$slug → $native",
                    'reason' => $norm === 'delivery_failed' ? 'delivery_failure' : null,
                ]);
            } catch (\Throwable $e) {
                // illegal transitions (e.g. delivered → out_for_delivery) get silently ignored.
            }
        }

        return response()->json(['ok' => true, 'mapped' => $norm]);
    }
}
