<?php

namespace App\Http\Controllers;

use App\Models\Exchange;
use App\Models\OrderMirror;
use App\Services\Orders\ExchangeException;
use App\Services\Orders\ExchangeIssuer;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;

class ExchangeController extends Controller
{
    public function index()
    {
        $exchanges = Exchange::with('original.store:id,dfid,business_name,name','replacement','requester')
            ->latest()->paginate(30);
        return view('exchanges.index', compact('exchanges'));
    }

    public function show(Exchange $exchange)
    {
        $exchange->load('original.items','replacement.items','requester');
        return view('exchanges.show', compact('exchange'));
    }

    /** Exchange request form: pick the NEW product(s) to send as the replacement. */
    public function create(OrderMirror $order)
    {
        if (! ExchangeIssuer::canExchange($order)) {
            return redirect()->route('orders.show', $order)
                ->with('error', "{$order->order_number} is {$order->statusLabel()} — only delivered orders can be exchanged.");
        }
        $order->load('items', 'store');
        return view('exchanges.create', compact('order'));
    }

    /** AJAX: search the order's store catalogue for the replacement product. */
    public function productSearch(Request $r, OrderMirror $order)
    {
        $rows = \App\Models\ProductMirror::where('store_id', $order->store_id)
            ->when($r->q, fn ($q, $t) => $q->where(fn ($x) => $x->where('sku', 'like', "%$t%")->orWhere('name', 'like', "%$t%")))
            ->orderBy('name')->limit(20)->get(['sku', 'name', 'price', 'sale_price', 'stock_quantity']);
        return response()->json($rows);
    }

    /**
     * Complete an exchange: release the replacement order into the warehouse
     * (→ Processing, ready to send to courier) and close the exchange.
     */
    public function complete(Exchange $exchange)
    {
        abort_if(in_array($exchange->status, ['completed', 'cancelled'], true), 422);

        if ($exchange->replacement && $exchange->replacement->status === OrderStateMachine::EXCHANGE_PROCESSING) {
            $exchange->replacement->forceFill([
                'status'        => OrderStateMachine::PROCESSING,
                'confirmed_at'  => $exchange->replacement->confirmed_at ?? now(),
                'processing_at' => now(),
            ])->save();
        }

        $exchange->update(['status' => 'completed', 'completed_at' => now()]);

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(), 'action' => 'exchange.complete',
            'entity_type' => Exchange::class, 'entity_id' => $exchange->id,
            'after_json' => ['status' => 'completed'], 'ip' => request()?->ip(),
        ]);

        return back()->with('status', 'Exchange completed — replacement order released to Processing.');
    }

    /** Cancel an exchange and its replacement order. */
    public function cancel(Exchange $exchange)
    {
        abort_if(in_array($exchange->status, ['completed', 'cancelled'], true), 422);

        if ($exchange->replacement) {
            $exchange->replacement->forceFill([
                'status'        => OrderStateMachine::CANCELLED,
                'cancelled_at'  => now(),
                'cancel_reason' => 'Exchange cancelled',
            ])->save();
        }

        $exchange->update(['status' => 'cancelled', 'completed_at' => now()]);

        return back()->with('status', 'Exchange cancelled — replacement order cancelled.');
    }

    /**
     * Issue an exchange for a delivered order (see ExchangeIssuer): the
     * replacement goes to Processing, the original awaits the returned product.
     */
    public function open(Request $r, OrderMirror $order, ExchangeIssuer $issuer)
    {
        $data = $r->validate([
            'reason'        => 'required|string|max:80',
            'notes'         => 'nullable|string|max:2000',
            'items'         => 'required|array|min:1',
            'items.*.sku'   => 'required|string|max:255',
            'items.*.qty'   => 'required|integer|min:1',
        ]);

        try {
            $ex = $issuer->issue($order, $data['items'], $data['reason'], $data['notes'] ?? null);
        } catch (ExchangeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('exchanges.show', $ex)->with('status',
            "Exchange issued — replacement {$ex->replacement->order_number} is in Processing; {$order->order_number} now awaits the returned product.");
    }
}
