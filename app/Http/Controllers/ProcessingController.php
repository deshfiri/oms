<?php

namespace App\Http\Controllers;

use App\Models\OrderMirror;
use App\Services\Couriers\CourierBookingFailedException;
use App\Services\Couriers\ShipmentBooker;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;

class ProcessingController extends Controller
{
    public function index(Request $r)
    {
        // Processing = confirmed orders NOT yet sent to the courier (no consignment).
        // Once the courier is booked the order moves to the Packing queue.
        $orders = OrderMirror::with('store:id,dfid,business_name,name')
            ->where('status', OrderStateMachine::PROCESSING)
            ->whereDoesntHave('consignments')
            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
            ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                $x->where('order_number','like',"%$t%")
                  ->orWhere('customer_name','like',"%$t%")
                  ->orWhere('customer_phone','like',"%$t%");
            }))
            ->latest('processing_at')->paginate(30);
        $stores = \App\Models\Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
        return view('processing.index', compact('orders','stores'));
    }

    public function rows(Request $r)
    {
        $orders = OrderMirror::with('store:id,dfid,business_name,name')
            ->where('status', OrderStateMachine::PROCESSING)
            ->whereDoesntHave('consignments')
            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
            ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                $x->where('order_number','like',"%$t%")
                  ->orWhere('customer_name','like',"%$t%")
                  ->orWhere('customer_phone','like',"%$t%");
            }))
            ->latest('processing_at')
            ->paginate(30)
            ->withPath(route('processing.index'))
            ->appends($r->except('page'));
        return response()->json([
            'tbody'      => view('processing._rows', compact('orders'))->render(),
            'pagination' => (string) $orders->links(),
            'total'      => $orders->total(),
        ]);
    }

    /**
     * Bulk courier entry: submit each parcel to the courier through the
     * storefront's Courier API, store the consignment, and render batch labels.
     * Status stays Processing until the physical-pack scan (or "Mark Packed").
     */
    public function bulkPack(Request $r, ShipmentBooker $booker)
    {
        $data = $r->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders_mirror,id',
        ]);
        $orders = OrderMirror::with('store','items')->whereIn('id', $data['order_ids'])
            ->where('status', OrderStateMachine::PROCESSING)->get();

        $booked = 0; $failed = 0; $skip = 0; $okIds = []; $reason = null;
        foreach ($orders as $order) {
            if ($order->consignments()->exists()) { $skip++; continue; } // already sent
            try {
                // Only a REAL consignment from the API gets created; otherwise this
                // throws and the order stays in Processing (no status change).
                $cons = $booker->book($order);
                $booked++; $okIds[] = $order->id;
            } catch (CourierBookingFailedException $e) {
                $failed++; $reason = $reason ?: $e->getMessage();
            } catch (\Throwable $e) {
                $failed++; $reason = $reason ?: $e->getMessage();
                \Log::warning('bulkPack courier registration failed', ['order' => $order->order_number, 'err' => $e->getMessage()]);
            }
        }

        // Only orders that actually booked get labels; failures stay in Processing.
        if ($booked === 0) {
            return back()->with('error', "Courier booking failed for $failed order(s) — they stay in Processing. ".($reason ? "Reason: {$reason} " : '')."Fix the courier, then send to courier again.");
        }
        $msg = "$booked booked with a real consignment — labels ready.";
        if ($failed) $msg .= " $failed failed and stayed in Processing".($reason ? " ({$reason})" : '').".";
        if ($skip)   $msg .= " $skip already sent.";
        return redirect()->route('labels.batch', ['ids' => implode(',', $okIds)])
            ->with($failed ? 'warning' : 'status', $msg);
    }

    /** Single-order courier registration — only succeeds with a REAL consignment. */
    public function startPacking(OrderMirror $order, ShipmentBooker $booker)
    {
        abort_unless($order->status === OrderStateMachine::PROCESSING, 422);
        if ($order->consignments()->exists()) {
            return redirect()->route('labels.single', $order)->with('status', 'Already sent to courier — label ready.');
        }
        try {
            $cons = $booker->book($order);
        } catch (CourierBookingFailedException $e) {
            // Order stays in Processing — no consignment, no status change.
            return back()->with('error', "Courier booking failed — {$order->order_number} stays in Processing. {$e->getMessage()}");
        } catch (\Throwable $e) {
            return back()->with('error', "Courier booking failed — {$order->order_number} stays in Processing. ".$e->getMessage());
        }
        return redirect()->route('labels.single', $order)
            ->with('status', "Courier booked — consignment {$cons->consignment_id}. Label ready — pack & scan to mark Packed.");
    }
}
