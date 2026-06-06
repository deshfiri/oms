<?php

namespace App\Http\Controllers;

use App\Models\LostLog;
use App\Models\OrderMirror;
use App\Models\Store;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;

class LostController extends Controller
{
    public function index()
    {
        $items = LostLog::with('store:id,dfid,business_name,name','order','recorder')->latest()->paginate(30);
        return view('lost.index', compact('items'));
    }

    public function create()
    {
        $stores = Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
        return view('lost.create', compact('stores'));
    }

    public function store(Request $r, OrderStateMachine $sm)
    {
        $data = $r->validate([
            'store_id'             => 'required|exists:stores,id',
            'order_id'             => 'nullable|exists:orders_mirror,id',
            'sku'                  => 'nullable|string|max:120',
            'qty'                  => 'required|integer|min:1',
            'responsible_party'    => 'required|in:'.implode(',', LostLog::PARTIES),
            'reason'               => 'nullable|string|max:200',
            'compensation_amount'  => 'nullable|numeric|min:0',
            'compensation_status'  => 'nullable|in:pending,paid,waived',
        ]);
        $log = LostLog::create($data + [
            'recorded_by' => auth()->id(),
            'recorded_at' => now(),
        ]);

        if (! empty($data['order_id'])) {
            $order = OrderMirror::find($data['order_id']);
            try { $sm->transition($order, OrderStateMachine::LOST, ['note' => $data['reason'] ?? null]); }
            catch (\Throwable) {}
        }
        return redirect()->route('lost.index')->with('status', 'Lost record saved.');
    }
}
