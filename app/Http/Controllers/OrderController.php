<?php

namespace App\Http\Controllers;

use App\Models\OrderMirror;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function show(OrderMirror $order)
    {
        $order->load(['items', 'store', 'pickingSessions.picker', 'packingSessions.packer', 'shipments', 'rmas']);
        return view('orders.show', compact('order'));
    }

    public function setStatus(Request $r, OrderMirror $order, \App\Services\Orders\OrderStateMachine $sm)
    {
        $data = $r->validate([
            'to'     => 'required|string',
            'note'   => 'nullable|string|max:2000',
            'reason' => 'nullable|string|max:80',
        ]);
        try {
            $meta = array_filter([
                'note'   => $data['note']   ?? null,
                'reason' => $data['reason'] ?? null,
            ], fn ($v) => $v !== null);
            $sm->transition($order, $data['to'], $meta);
            return back()->with('status', 'Status → '.$sm->label($data['to']));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $r, OrderMirror $order, \App\Services\Orders\OrderStateMachine $sm)
    {
        $data = $r->validate([
            'reason' => 'nullable|string|max:80',
            'note'   => 'nullable|string|max:2000',
        ]);
        try {
            $sm->transition($order, \App\Services\Orders\OrderStateMachine::CANCELLED, array_filter([
                'reason' => $data['reason'] ?? null,
                'note'   => $data['note']   ?? null,
            ], fn ($v) => $v !== null));
            return back()->with('status', 'Order cancelled');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
