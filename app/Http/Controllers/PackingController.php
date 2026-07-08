<?php

namespace App\Http\Controllers;

use App\Models\CourierConsignment;
use App\Models\OrderMirror;
use App\Models\PackingSession;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /** Manual packing form — enter weight, dimensions and courier by hand. */
    public function show(OrderMirror $order)
    {
        abort_unless(
            in_array($order->status, [OrderStateMachine::PROCESSING, OrderStateMachine::PACKED]),
            422
        );
        return view('packing.show', compact('order'));
    }

    /**
     * Complete manual packing: record dimensions, create a consignment from
     * the operator-supplied tracking number, then transition to Packed.
     */
    public function complete(Request $r, OrderMirror $order, OrderStateMachine $sm)
    {
        abort_unless($order->status === OrderStateMachine::PROCESSING, 422);

        $data = $r->validate([
            'weight_kg'     => 'required|numeric|min:0.001',
            'length_cm'     => 'nullable|numeric|min:0',
            'width_cm'      => 'nullable|numeric|min:0',
            'height_cm'     => 'nullable|numeric|min:0',
            'courier'       => 'required|string|max:50',
            'tracking_code' => 'required|string|max:200',
            'cod_amount'    => 'nullable|numeric|min:0',
        ]);

        if ($order->consignments()->exists()) {
            return redirect()->route('labels.single', $order)
                ->with('status', 'Already has a consignment — label ready.');
        }

        $courierLabels = [
            'steadfast' => 'Steadfast',
            'pathao'    => 'Pathao',
            'carrybee'  => 'CarryBee',
            'redx'      => 'RedX',
            'manual'    => 'Manual',
        ];

        $cod = (float) ($data['cod_amount'] ?? 0);

        DB::transaction(function () use ($order, $data, $cod, $courierLabels, $r, $sm) {
            CourierConsignment::create([
                'order_id'       => $order->id,
                'courier_slug'   => $data['courier'],
                'courier_name'   => $courierLabels[$data['courier']] ?? ucfirst($data['courier']),
                'consignment_id' => null,
                'tracking_code'  => $data['tracking_code'],
                'tracking_url'   => null,
                'label_url'      => null,
                'cod_amount'     => $cod,
                'weight_kg'      => $data['weight_kg'],
                'latest_status'  => 'booked',
                'raw_payload'    => ['source' => 'manual_packing'],
                'booked_at'      => now(),
            ]);

            PackingSession::create([
                'order_id'       => $order->id,
                'store_id'       => $order->store_id,
                'packer_user_id' => $r->user()->id,
                'weight_kg'      => $data['weight_kg'],
                'length_cm'      => $data['length_cm'] ?? null,
                'width_cm'       => $data['width_cm'] ?? null,
                'height_cm'      => $data['height_cm'] ?? null,
                'courier'        => $data['courier'],
                'tracking_code'  => $data['tracking_code'],
                'packed_at'      => now(),
            ]);

            $sm->transition($order, OrderStateMachine::PACKED, [
                'note' => 'Manual pack — '.$data['courier'].', tracking: '.$data['tracking_code'],
            ]);
        });

        return redirect()->route('labels.single', $order)
            ->with('status', 'Packed — label ready.');
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
