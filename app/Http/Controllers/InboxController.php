<?php

namespace App\Http\Controllers;

use App\Models\OrderMirror;
use App\Models\Store;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $r)
    {
        $q = OrderMirror::query()
            ->with('store:id,dfid,business_name,name')
            ->when($r->store_id, fn($q, $id) => $q->where('store_id', $id))
            ->when($r->status, fn($q,$s) => $q->where('status', $s), fn($q) => $q->whereIn('status', ['pending','confirmed']))
            ->when($r->q, function ($q,$term) {
                $q->where(function ($x) use ($term) {
                    $x->where('order_number', 'like', "%$term%")
                      ->orWhere('customer_name', 'like', "%$term%")
                      ->orWhere('customer_phone', 'like', "%$term%");
                });
            })
            ->when($r->payment, fn($q,$p) => $q->where('payment_method', $p))
            ->latest('placed_at');

        $orders = $q->paginate(25)->withQueryString();
        $stores = Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
        return view('inbox.index', compact('orders','stores'));
    }
}
