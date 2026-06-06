<?php

namespace App\Http\Controllers;

use App\Models\CourierConsignment;
use App\Models\OrderMirror;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DispatchController extends Controller
{
    public function index(Request $r)
    {
        // Packed parcels waiting to be handed over.
        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'consignments')
            ->where('status', OrderStateMachine::PACKED)
            ->latest('packed_at')
            ->get()
            ->groupBy(fn ($o) => optional($o->consignments->last())->courier_slug ?? 'manual');

        // Capability matrix so the view shows honest "tracking not provided"
        // vs "pending" instead of faking a value.
        $courierCaps = collect(\App\Services\Couriers\CourierManager::ADAPTERS)
            ->mapWithKeys(fn ($cls, $slug) => [$slug => app($cls)->capabilities()])
            ->all();

        return view('dispatch.index', compact('orders', 'courierCaps'));
    }

    public function handover(Request $r, OrderMirror $order, OrderStateMachine $sm)
    {
        try {
            $sm->transition($order, OrderStateMachine::DISPATCHED, ['note' => 'Handed to courier']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('status', "{$order->order_number} handed over.");
    }

    /** Bulk mark selected Packed orders as Dispatched (handed to courier). */
    public function bulkHandover(Request $r, OrderStateMachine $sm)
    {
        $data = $r->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders_mirror,id',
        ]);
        $orders = OrderMirror::whereIn('id', $data['order_ids'])
            ->where('status', OrderStateMachine::PACKED)->get();
        $ok = 0; $fail = 0;
        foreach ($orders as $order) {
            try { $sm->transition($order, OrderStateMachine::DISPATCHED, ['note' => 'Bulk handed over']); $ok++; }
            catch (\Throwable $e) { $fail++; }
        }
        return back()->with('status', "$ok dispatched, $fail failed.");
    }

    public function exportCsv(Request $r): StreamedResponse
    {
        $courier = $r->query('courier');
        $rows = OrderMirror::with('store','consignments')
            ->where('status', OrderStateMachine::PACKED)
            ->when($courier, fn($q,$c) => $q->whereHas('consignments', fn($x) => $x->where('courier_slug', $c)))
            ->get();
        return response()->streamDownload(function () use ($rows) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['order_number','dfid','recipient','phone','address','city','cod','courier','consignment_id','tracking']);
            foreach ($rows as $o) {
                $c = $o->consignments->last();
                fputcsv($h, [
                    $o->order_number, $o->store->dfid ?? '', $o->shipping_name, $o->shipping_phone,
                    $o->shipping_address_line, $o->shipping_city,
                    $o->payment_method === 'cod' ? $o->grand_total : 0,
                    $c?->courier_slug, $c?->consignment_id, $c?->tracking_code,
                ]);
            }
            fclose($h);
        }, 'manifest-'.now()->format('Ymd-Hi').($courier ? "-$courier" : '').'.csv');
    }
}
