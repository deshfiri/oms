<?php

namespace App\Http\Controllers;

use App\Models\OrderMirror;
use App\Services\Couriers\CourierBookingFailedException;
use App\Services\Couriers\CourierPicker;
use App\Services\Couriers\ShipmentBooker;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;

class ProcessingController extends Controller
{
    public function index(Request $r)
    {
        // Processing = confirmed orders NOT yet sent to the courier (no consignment).
        // Once the courier is booked the order moves to the Packing queue.
        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS, 'exchangeOriginal:id,order_number')
            ->where('status', OrderStateMachine::PROCESSING)
            ->whereDoesntHave('consignments')
            ->storeFilter($r->store_id, $r->store)
            ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                $x->where('order_number','like',"%$t%")
                  ->orWhere('customer_name','like',"%$t%")
                  ->orWhere('customer_phone','like',"%$t%");
            }))
            ->latest('processing_at')->paginate(30);
        $stores = \App\Models\Store::forPicker();
        return view('processing.index', compact('orders','stores'));
    }

    public function rows(Request $r)
    {
        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS, 'exchangeOriginal:id,order_number')
            ->where('status', OrderStateMachine::PROCESSING)
            ->whereDoesntHave('consignments')
            ->storeFilter($r->store_id, $r->store)
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

    /**
     * Courier booking page for an exchange replacement order. Steadfast can't
     * create exchange parcels over its API, so for Steadfast stores this shows
     * what to enter in the Steadfast panel and takes the consignment ID back;
     * other couriers use the normal "Send to courier" booking.
     */
    public function exchangeBooking(OrderMirror $order, CourierPicker $picker)
    {
        abort_unless($order->exchange_of_order_id, 404);
        if ($order->consignments()->exists()) {
            return redirect()->route('labels.single', $order)->with('status', 'Already sent to courier — label ready.');
        }
        abort_unless($order->status === OrderStateMachine::PROCESSING, 422);

        try {
            $slug = $picker->pickFor($order);
            $courierError = null;
        } catch (\Throwable $e) {
            $slug = null;
            $courierError = $e->getMessage();
        }

        $order->load('items', 'store', 'exchangeOriginal.items');
        return view('processing.exchange-booking', [
            'order'        => $order,
            'slug'         => $slug,
            'manual'       => $slug && ShipmentBooker::needsManualExchangeBooking($order, $slug),
            'courierError' => $courierError,
            'cod'          => $order->payment_method === 'cod' ? (float) $order->grand_total : 0.0,
        ]);
    }

    /** Record the Steadfast exchange parcel's consignment — saved only once Steadfast confirms it. */
    public function attachConsignment(Request $r, OrderMirror $order, ShipmentBooker $booker)
    {
        $data = $r->validate([
            'consignment_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'tracking_code'  => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ], [
            'consignment_id.regex' => 'The consignment ID may only contain letters, digits, - and _.',
            'tracking_code.regex'  => 'The tracking code may only contain letters, digits, - and _.',
        ]);
        abort_unless($order->exchange_of_order_id && $order->status === OrderStateMachine::PROCESSING, 422);

        try {
            $cons = $booker->attachManual($order, $data['consignment_id'], $data['tracking_code'] ?? null);
        } catch (CourierBookingFailedException $e) {
            return back()->withInput()->with('error', "Consignment not saved — {$order->order_number} stays in Processing. {$e->getMessage()}");
        }

        return redirect()->route('labels.single', $order)
            ->with('status', "Steadfast exchange consignment {$cons->consignment_id} verified & saved. Label ready — pack & scan to mark Packed.");
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
