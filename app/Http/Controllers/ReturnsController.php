<?php

namespace App\Http\Controllers;

use App\Models\DamageLog;
use App\Models\OrderMirror;
use App\Services\Inventory\InventoryEngine;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;

class ReturnsController extends Controller
{
    public function index(Request $r)
    {
        $states = [
            OrderStateMachine::RETURN_PENDING,
            OrderStateMachine::RETURNED,
            OrderStateMachine::AWAITING_RETURN_PRODUCT,
        ];

        $orders = OrderMirror::with('store:id,dfid,business_name,name')
            ->whereIn('status', $states)
            ->when($r->status, fn($q,$s) => $q->where('status', $s))
            ->latest('return_pending_at')->paginate(30)->withQueryString();

        // Counts per state for the filter chips.
        $counts = OrderMirror::query()->whereIn('status', $states)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('returns.index', compact('orders', 'counts'));
    }

    /**
     * Warehouse-initiated return: take a dispatched/shipped/out-for-delivery/
     * partial/delivered order and move it into Return Pending. From there it's
     * Received → Inspected like any other return.
     */
    public function start(Request $r, OrderStateMachine $sm)
    {
        $data = $r->validate([
            'order_number' => 'required|string|max:120',
            'reason'       => 'nullable|string|max:160',
        ]);

        $order = OrderMirror::where('order_number', $data['order_number'])->first();
        if (! $order) {
            return back()->with('error', "No order found for “{$data['order_number']}”.");
        }

        $returnable = [
            OrderStateMachine::DISPATCHED, OrderStateMachine::SHIPPED,
            OrderStateMachine::OUT_FOR_DELIVERY, OrderStateMachine::PARTIAL_DELIVERY,
            OrderStateMachine::DELIVERED,
        ];
        if (! in_array($order->status, $returnable, true)) {
            return back()->with('error', "{$order->order_number} is {$order->statusLabel()} — only dispatched/shipped/out-for-delivery/delivered orders can be returned.");
        }

        try {
            $sm->transition($order, OrderStateMachine::RETURN_PENDING, array_filter([
                'reason' => $data['reason'] ?? 'customer_return',
            ]));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Return started for {$order->order_number} — receive the parcel when it arrives.");
    }

    /**
     * Mark a returned parcel as physically received at the warehouse
     * (RETURN_PENDING | AWAITING_RETURN_PRODUCT → RETURNED) so it can be
     * inspected. Same effect as scanning it in — but without the scanner.
     */
    public function receive(OrderMirror $order, OrderStateMachine $sm)
    {
        abort_unless(in_array($order->status, [
            OrderStateMachine::RETURN_PENDING,
            OrderStateMachine::AWAITING_RETURN_PRODUCT,
        ], true), 422);

        try {
            $sm->transition($order, OrderStateMachine::RETURNED, ['note' => 'Return parcel received at warehouse']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('status', "{$order->order_number} received — ready to inspect.");
    }

    public function inspect(Request $r, OrderMirror $order, OrderStateMachine $sm, InventoryEngine $inv)
    {
        abort_unless($order->status === OrderStateMachine::RETURNED, 422);
        $data = $r->validate([
            'decision'         => 'required|in:restock,damage',
            'condition_grade'  => 'nullable|in:A,B,C,D',
            'inspection_notes' => 'nullable|string|max:2000',
            'photos.*'         => 'nullable|image|max:5120',
            'damaged_qty'      => 'nullable|integer|min:1',
            'responsible_party'=> 'nullable|in:warehouse,courier,vendor',
            'damage_reason'    => 'nullable|string|max:160',
        ]);
        $photos = [];
        if ($r->hasFile('photos')) {
            foreach ($r->file('photos') as $f) $photos[] = $f->store('returns', 'public');
        }

        if ($data['decision'] === 'restock') {
            try {
                $sm->transition($order, OrderStateMachine::RESTOCKABLE, [
                    'note' => $data['inspection_notes'] ?? null,
                ]);
            } catch (\Throwable $e) { return back()->with('error', $e->getMessage()); }
            return back()->with('status', 'Restocked into available inventory.');
        }

        // Damage path — also record on the damage_log
        try {
            $sm->transition($order, OrderStateMachine::DAMAGED, [
                'note' => $data['inspection_notes'] ?? null,
            ]);
        } catch (\Throwable $e) { return back()->with('error', $e->getMessage()); }

        foreach ($order->items as $it) {
            DamageLog::create([
                'store_id'    => $order->store_id,
                'product_id'  => $it->product_id,
                'sku'         => $it->sku,
                'qty'         => $data['damaged_qty'] ?? $it->qty,
                'reason'      => $data['damage_reason'] ?? 'return-inspection',
                'photos_json' => $photos,
                'recorded_by' => auth()->id(),
                'recorded_at' => now(),
            ]);
        }
        return back()->with('status', 'Recorded as damaged (no restock).');
    }
}
