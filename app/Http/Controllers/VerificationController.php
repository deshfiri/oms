<?php

namespace App\Http\Controllers;

use App\Models\OrderMirror;
use App\Models\OrderVerification;
use App\Services\Orders\OrderStateMachine;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function index(Request $r)
    {
        $orders = OrderMirror::with('store:id,dfid,business_name,name')
            ->where('status', OrderStateMachine::PENDING_VERIFICATION)
            ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                $x->where('order_number','like',"%$t%")
                  ->orWhere('customer_name','like',"%$t%")
                  ->orWhere('customer_phone','like',"%$t%");
            }))
            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
            ->latest('placed_at')->paginate(25);
        $stores = \App\Models\Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
        return view('verification.index', compact('orders','stores'));
    }

    public function rows(Request $r)
    {
        $orders = OrderMirror::with('store:id,dfid,business_name,name')
            ->where('status', OrderStateMachine::PENDING_VERIFICATION)
            ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                $x->where('order_number','like',"%$t%")
                  ->orWhere('customer_name','like',"%$t%")
                  ->orWhere('customer_phone','like',"%$t%");
            }))
            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
            ->latest('placed_at')
            ->paginate(25)
            ->withPath(route('verification.index'))
            ->appends($r->except('page'));
        return response()->json([
            'tbody'      => view('verification._rows', compact('orders'))->render(),
            'pagination' => (string) $orders->links(),
            'total'      => $orders->total(),
        ]);
    }

    public function show(OrderMirror $order)
    {
        abort_unless($order->status === OrderStateMachine::PENDING_VERIFICATION, 404);
        $order->load('items','store','verifications.agent');
        return view('verification.show', compact('order'));
    }

    public function update(Request $r, OrderMirror $order)
    {
        abort_unless($order->status === OrderStateMachine::PENDING_VERIFICATION, 422);
        $data = $r->validate([
            'customer_name'         => 'nullable|string|max:120',
            'customer_phone'        => 'nullable|string|max:40',
            'shipping_name'         => 'nullable|string|max:120',
            'shipping_phone'        => 'nullable|string|max:40',
            'shipping_address_line' => 'nullable|string|max:300',
            'shipping_area'         => 'nullable|string|max:120',
            'shipping_city'         => 'nullable|string|max:120',
            'shipping_district'     => 'nullable|string|max:120',
            'shipping_postcode'     => 'nullable|string|max:20',
            'shipping_zone'         => 'nullable|string|max:60',
            'discount'              => 'nullable|numeric|min:0',
            'coupon_code'           => 'nullable|string|max:60',
            'coupon_discount'       => 'nullable|numeric|min:0',
            'shipping_total'        => 'nullable|numeric|min:0',   // §3: CS may modify courier charges
            'notes'                 => 'nullable|string|max:2000',
        ]);

        // Diff for the verification log
        $before = $order->only(array_keys($data));
        $order->update(array_filter($data, fn($v) => $v !== null));
        // Recompute grand total
        $itemsSubtotal = $order->items()->sum(\DB::raw('qty * unit_price'));
        $order->update([
            'subtotal'    => $itemsSubtotal,
            'grand_total' => max(0, $itemsSubtotal - ($order->discount ?? 0) - ($order->coupon_discount ?? 0) + ($order->shipping_total ?? 0)),
        ]);
        OrderVerification::create([
            'order_id'      => $order->id,
            'agent_user_id' => auth()->id(),
            'call_outcome'  => 'edited',
            'action'        => 'edited',
            'changes_json'  => ['before' => $before, 'after' => $data],
            'attempted_at'  => now(),
        ]);
        return back()->with('status','Order details updated.');
    }

    public function addItem(Request $r, OrderMirror $order)
    {
        abort_unless($order->status === OrderStateMachine::PENDING_VERIFICATION, 422);
        $data = $r->validate([
            'sku' => 'required|string|max:120',
            'qty' => 'required|integer|min:1',
        ]);
        // Pull canonical name + unit price from the product mirror — CS does NOT set the price.
        $product = \App\Models\ProductMirror::where('store_id', $order->store_id)
            ->where('sku', $data['sku'])->first();
        if (! $product) {
            return back()->with('error', "SKU {$data['sku']} not in store catalogue.");
        }
        $unit = (float) ($product->sale_price ?? $product->price);
        $line = $order->items()->create([
            'product_id' => $product->product_id,
            'sku'        => $data['sku'],
            'name'       => $product->name,
            'qty'        => $data['qty'],
            'unit_price' => $unit,
            'line_total' => $data['qty'] * $unit,
        ]);
        $this->recompute($order);
        return back()->with('status', "Added {$line->name}");
    }

    public function updateItem(Request $r, OrderMirror $order, \App\Models\OrderItemMirror $item)
    {
        abort_unless($item->order_id === $order->id, 404);
        abort_unless($order->status === OrderStateMachine::PENDING_VERIFICATION, 422);
        // CS can change qty only — never the unit price (it's catalogue-controlled).
        $data = $r->validate(['qty' => 'required|integer|min:1']);
        $item->update([
            'qty'        => $data['qty'],
            'line_total' => $data['qty'] * (float) $item->unit_price,
        ]);
        $this->recompute($order);
        return back()->with('status', 'Quantity updated.');
    }

    public function removeItem(OrderMirror $order, \App\Models\OrderItemMirror $item)
    {
        abort_unless($item->order_id === $order->id, 404);
        abort_unless($order->status === OrderStateMachine::PENDING_VERIFICATION, 422);
        $item->delete();
        $this->recompute($order);
        return back()->with('status', 'Item removed.');
    }

    public function confirm(Request $r, OrderMirror $order, OrderStateMachine $sm)
    {
        abort_unless($order->status === OrderStateMachine::PENDING_VERIFICATION, 422);
        $data = $r->validate(['call_outcome'=>'required|string|max:40','summary'=>'nullable|string|max:2000']);
        OrderVerification::create([
            'order_id'      => $order->id,
            'agent_user_id' => auth()->id(),
            'call_outcome'  => $data['call_outcome'],
            'action'        => 'confirmed',
            'summary'       => $data['summary'] ?? null,
            'attempted_at'  => now(),
        ]);
        $order->update(['verifier_user_id' => auth()->id()]);
        try {
            $sm->transition($order, OrderStateMachine::CONFIRMED, ['note' => $data['summary'] ?? null]);
            // auto-flow to processing per spec
            $sm->transition($order->refresh(), OrderStateMachine::PROCESSING, ['note' => 'Auto-routed to warehouse']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('verification.index')->with('status','Order confirmed and routed to warehouse.');
    }

    public function cancel(Request $r, OrderMirror $order, OrderStateMachine $sm)
    {
        abort_unless($order->status === OrderStateMachine::PENDING_VERIFICATION, 422);
        $data = $r->validate([
            'reason'  => 'required|in:'.implode(',', OrderMirror::CANCEL_REASONS),
            'summary' => 'nullable|string|max:2000',
        ]);
        OrderVerification::create([
            'order_id'      => $order->id,
            'agent_user_id' => auth()->id(),
            'call_outcome'  => 'contacted',
            'action'        => 'cancelled',
            'summary'       => $data['summary'] ?? null,
            'attempted_at'  => now(),
        ]);
        try {
            $sm->transition($order, OrderStateMachine::CANCELLED, [
                'reason' => $data['reason'],
                'note'   => $data['summary'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('verification.index')->with('status', 'Order cancelled.');
    }

    /** §16 — bulk confirm selected pending orders, auto-route each to warehouse. */
    public function bulkConfirm(Request $r, OrderStateMachine $sm)
    {
        $data = $r->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders_mirror,id',
        ]);
        $orders = OrderMirror::whereIn('id', $data['order_ids'])
            ->where('status', OrderStateMachine::PENDING_VERIFICATION)->get();

        $ok = 0; $fail = 0;
        foreach ($orders as $order) {
            try {
                OrderVerification::create([
                    'order_id'      => $order->id,
                    'agent_user_id' => auth()->id(),
                    'call_outcome'  => 'contacted',
                    'action'        => 'confirmed',
                    'summary'       => 'Bulk confirmed',
                    'attempted_at'  => now(),
                ]);
                $order->update(['verifier_user_id' => auth()->id()]);
                $sm->transition($order, OrderStateMachine::CONFIRMED, ['note' => 'Bulk confirmed']);
                $sm->transition($order->refresh(), OrderStateMachine::PROCESSING, ['note' => 'Auto-routed to warehouse']);
                $ok++;
            } catch (\Throwable $e) { $fail++; }
        }
        return back()->with('status', "$ok confirmed & routed to warehouse, $fail failed.");
    }

    /** §16 — bulk cancel selected pending orders with a shared reason. */
    public function bulkCancel(Request $r, OrderStateMachine $sm)
    {
        $data = $r->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders_mirror,id',
            'reason'      => 'required|in:'.implode(',', OrderMirror::CANCEL_REASONS),
            'summary'     => 'nullable|string|max:2000',
        ]);
        $orders = OrderMirror::whereIn('id', $data['order_ids'])
            ->where('status', OrderStateMachine::PENDING_VERIFICATION)->get();

        $ok = 0; $fail = 0;
        foreach ($orders as $order) {
            try {
                OrderVerification::create([
                    'order_id'      => $order->id,
                    'agent_user_id' => auth()->id(),
                    'call_outcome'  => 'contacted',
                    'action'        => 'cancelled',
                    'summary'       => $data['summary'] ?? 'Bulk cancelled',
                    'attempted_at'  => now(),
                ]);
                $sm->transition($order, OrderStateMachine::CANCELLED, [
                    'reason' => $data['reason'],
                    'note'   => $data['summary'] ?? 'Bulk cancelled',
                ]);
                $ok++;
            } catch (\Throwable $e) { $fail++; }
        }
        return back()->with('status', "$ok cancelled, $fail failed.");
    }

    protected function recompute(OrderMirror $order): void
    {
        $subtotal = $order->items()->sum(\DB::raw('qty * unit_price'));
        $order->update([
            'subtotal'    => $subtotal,
            'grand_total' => max(0, $subtotal - ($order->discount ?? 0) - ($order->coupon_discount ?? 0) + ($order->shipping_total ?? 0)),
        ]);
    }
}
