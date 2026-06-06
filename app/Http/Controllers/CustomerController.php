<?php

namespace App\Http\Controllers;

use App\Models\CustomerMirror;
use App\Models\OrderMirror;
use App\Models\Store;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $r)
    {
        $local = CustomerMirror::with('store:id,dfid,business_name,name')
            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
            ->when($r->q, fn($q,$t) => $q->where(function ($x) use ($t) {
                $x->where('name','like',"%$t%")
                  ->orWhere('email','like',"%$t%")
                  ->orWhere('phone','like',"%$t%");
            }))
            ->limit(50)->get();

        // If query and no local hits, fan out to every active store's API.
        $remote = [];
        if ($r->q && $local->isEmpty()) {
            $stores = Store::where('is_active', true)->get();
            foreach ($stores as $store) {
                try {
                    $resp = (new StorefrontClient($store))->customers()->list(['q' => $r->q]);
                    foreach (($resp['data'] ?? []) as $row) {
                        $remote[] = $row + ['_store' => $store->business_name ?? $store->name];
                    }
                } catch (\Throwable) {}
            }
        }

        return view('customers.index', ['customers' => $local, 'remote' => $remote]);
    }

    public function show(CustomerMirror $customer)
    {
        $customer->load('store:id,dfid,business_name,name');
        $orders = OrderMirror::with('store:id,dfid,business_name,name')
            ->where('store_id', $customer->store_id)
            ->where(function ($q) use ($customer) {
                $q->where('customer_email', $customer->email)
                  ->orWhere('customer_phone', $customer->phone);
            })->latest('placed_at')->limit(50)->get();
        return view('customers.show', compact('customer','orders'));
    }
}
