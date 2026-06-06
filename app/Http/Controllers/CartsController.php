<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\Storefront\StorefrontClient;

class CartsController extends Controller
{
    public function index()
    {
        $abandoned = $incomplete = [];
        foreach (Store::where('is_active', true)->get() as $store) {
            $client = new StorefrontClient($store);
            $label = $store->business_name ?? $store->name;
            try {
                foreach (($client->carts()->abandoned()['data'] ?? []) as $row) {
                    $abandoned[] = $row + ['_store' => $label];
                }
            } catch (\Throwable) {}
            try {
                foreach (($client->carts()->incompleteOrders()['data'] ?? []) as $row) {
                    $incomplete[] = $row + ['_store' => $label];
                }
            } catch (\Throwable) {}
        }
        return view('carts.index', compact('abandoned','incomplete'));
    }
}
