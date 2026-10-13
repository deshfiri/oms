<?php

namespace App\Http\Controllers;

use App\Models\OrderMirror;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function index(Request $r)
    {
        $refunds = OrderMirror::with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS)
            ->whereNotNull('refund_status')
            ->when($r->status, fn($q, $s) => $q->where('refund_status', $s))
            ->storeFilter($r->store_id, $r->store)
            ->latest('refunded_at')
            ->paginate(30)
            ->withQueryString();

        $counts = OrderMirror::whereNotNull('refund_status')
            ->storeFilter($r->store_id, $r->store)
            ->selectRaw('refund_status, COUNT(*) as c')
            ->groupBy('refund_status')
            ->pluck('c', 'refund_status');

        return view('refunds.index', compact('refunds', 'counts'));
    }

    /**
     * Issue a refund for an order. Called from the inspect form or standalone.
     * Sets refund_status = pending, attempts storefront API call, then sets
     * refunded or failed accordingly.
     */
    public function issue(Request $r, OrderMirror $order)
    {
        abort_unless(in_array($order->status, [
            \App\Services\Orders\OrderStateMachine::RETURNED,
            \App\Services\Orders\OrderStateMachine::DAMAGED,
            \App\Services\Orders\OrderStateMachine::RESTOCKABLE,
            \App\Services\Orders\OrderStateMachine::DELIVERED,
            \App\Services\Orders\OrderStateMachine::PARTIAL_DELIVERY,
        ], true), 422, 'Order not in a refundable state.');

        $data = $r->validate([
            'refund_amount' => 'required|numeric|min:0.01',
            'refund_note'   => 'nullable|string|max:255',
        ]);

        $order->update([
            'refund_status' => 'pending',
            'refund_amount' => $data['refund_amount'],
            'refund_note'   => $data['refund_note'] ?? null,
        ]);

        try {
            (new StorefrontClient($order->store))->orders()->refund($order->order_number, [
                'amount' => $data['refund_amount'],
                'note'   => $data['refund_note'] ?? '',
            ]);
            $order->update(['refund_status' => 'refunded', 'refunded_at' => now()]);
            return back()->with('status', "Refund of ৳{$data['refund_amount']} issued for {$order->order_number}.");
        } catch (\Throwable $e) {
            $order->update(['refund_status' => 'failed']);
            return back()->with('error', "Refund failed for {$order->order_number}: " . $e->getMessage());
        }
    }

    /**
     * Retry a failed refund.
     */
    public function retry(OrderMirror $order)
    {
        abort_unless($order->refund_status === 'failed', 422, 'Only failed refunds can be retried.');

        $order->update(['refund_status' => 'pending']);

        try {
            (new StorefrontClient($order->store))->orders()->refund($order->order_number, [
                'amount' => $order->refund_amount,
                'note'   => $order->refund_note ?? '',
            ]);
            $order->update(['refund_status' => 'refunded', 'refunded_at' => now()]);
            return back()->with('status', "Refund of ৳{$order->refund_amount} issued for {$order->order_number}.");
        } catch (\Throwable $e) {
            $order->update(['refund_status' => 'failed']);
            return back()->with('error', "Retry failed: " . $e->getMessage());
        }
    }

    /**
     * Cancel a pending refund (admin override — no money was moved yet).
     */
    public function cancel(OrderMirror $order)
    {
        abort_unless($order->refund_status === 'pending', 422, 'Only pending refunds can be cancelled.');
        $order->update(['refund_status' => 'cancelled']);
        return back()->with('status', "Refund cancelled for {$order->order_number}.");
    }
}
