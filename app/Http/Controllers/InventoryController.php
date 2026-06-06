<?php

namespace App\Http\Controllers;

use App\Models\ProductMirror;
use App\Models\Store;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $r)
    {
        $products = ProductMirror::with('store:id,dfid,business_name,name')
            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
            ->when($r->q, fn($q,$t) => $q->where(function ($x) use ($t) {
                $x->where('sku','like',"%$t%")->orWhere('name','like',"%$t%");
            }))
            ->when($r->low, fn($q) => $q->whereColumn('stock_quantity','<=','low_stock_threshold')->where('manage_stock',true))
            ->orderBy('name')->paginate(50)->withQueryString();
        $stores = Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
        return view('inventory.index', compact('products','stores'));
    }
}
