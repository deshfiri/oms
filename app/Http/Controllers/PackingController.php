<?php

namespace App\Http\Controllers;

use App\Models\OrderMirror;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;

/**
 * Packing queue — orders consigned to a courier (consignment + label exist),
 * being physically packed and waiting to be marked Packed.
 */
class PackingController extends Controller
{
    public function index(Request $r)
    {
        $orders = OrderMirror::with('store:id,dfid,business_name,name','consignments','packer:id,name')
            ->where('status', OrderStateMachine::PROCESSING)
            ->whereHas('consignments')
            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
            ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                $x->where('order_number','like',"%$t%")
                  ->orWhere('customer_name','like',"%$t%")
                  ->orWhere('customer_phone','like',"%$t%")
                  ->orWhereHas('consignments', fn($c) => $c->where('tracking_code','like',"%$t%")->orWhere('consignment_id','like',"%$t%"));
            }))
            ->latest('processing_at')->paginate(30);
        $stores = \App\Models\Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
        return view('packing.index', compact('orders','stores'));
    }

    public function rows(Request $r)
    {
        $orders = OrderMirror::with('store:id,dfid,business_name,name','consignments','packer:id,name')
            ->where('status', OrderStateMachine::PROCESSING)
            ->whereHas('consignments')
            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
            ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                $x->where('order_number','like',"%$t%")
                  ->orWhere('customer_name','like',"%$t%")
                  ->orWhere('customer_phone','like',"%$t%")
                  ->orWhereHas('consignments', fn($c) => $c->where('tracking_code','like',"%$t%")->orWhere('consignment_id','like',"%$t%"));
            }))
            ->latest('processing_at')
            ->paginate(30)
            ->withPath(route('packing.index'))
            ->appends($r->except('page'));
        return response()->json([
            'tbody'      => view('packing._rows', compact('orders'))->render(),
            'pagination' => (string) $orders->links(),
            'total'      => $orders->total(),
        ]);
    }

    /** Mark a single order Packed (after physically sealing the parcel). */
    public function markPacked(OrderMirror $order, OrderStateMachine $sm)
    {
        abort_unless($order->status === OrderStateMachine::PROCESSING, 422);
        if (! $order->consignments()->exists()) {
            return back()->with('error', 'Send the parcel to the courier first (no consignment yet).');
        }
        try {
            $sm->transition($order, OrderStateMachine::PACKED, ['note' => 'Marked packed']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('status', "{$order->order_number} marked Packed.");
    }

    /** Bulk mark selected Packing orders as Packed. */
    public function bulkMarkPacked(Request $r, OrderStateMachine $sm)
    {
        $data = $r->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders_mirror,id',
        ]);
        $orders = OrderMirror::whereIn('id', $data['order_ids'])
            ->where('status', OrderStateMachine::PROCESSING)
            ->whereHas('consignments')->get();
        $ok = 0; $fail = 0;
        foreach ($orders as $order) {
            try { $sm->transition($order, OrderStateMachine::PACKED, ['note' => 'Bulk marked packed']); $ok++; }
            catch (\Throwable $e) { $fail++; }
        }
        return back()->with('status', "$ok marked Packed, $fail failed.");
    }
}
