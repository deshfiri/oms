<?php

namespace App\Http\Controllers;

use App\Models\OrderMirror;
use App\Models\ProductMirror;
use App\Models\Store;
use App\Services\Orders\OrderStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Social-media-manager (and admin) flow for creating an order from inside OMS.
 *
 * SMM users are pinned to specific stores via the user_stores pivot — they can
 * only create orders for those stores. New orders enter the lifecycle at
 * Pending Verification so the CS team can call the customer and confirm.
 *
 * Until confirmation, the order's *creator* may delete it. Once it's
 * Confirmed (or further), delete is locked.
 */
class OrderCreationController extends Controller
{
    /** "My orders" — what this SMM has placed; admins see all SMM-placed orders. */
    public function index(Request $r)
    {
        $u = $r->user();
        $q = OrderMirror::with('store:id,dfid,business_name,name','placedBy:id,name')
            ->whereNotNull('placed_by_user_id')
            ->when(! $u->isAdmin(), fn($q) => $q->where('placed_by_user_id', $u->id))
            ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                $x->where('order_number','like',"%$t%")
                  ->orWhere('customer_name','like',"%$t%")
                  ->orWhere('customer_phone','like',"%$t%");
            }))
            ->latest('placed_at');
        $orders = $q->paginate(25)->withQueryString();
        return view('orders-new.index', compact('orders'));
    }

    public function create(Request $r)
    {
        $u = $r->user();
        $stores = $u->isAdmin()
            ? Store::where('is_active', true)->orderBy('business_name')->get()
            : $u->stores()->where('is_active', true)->get();
        if ($stores->isEmpty()) {
            return back()->with('error', 'No stores assigned to you. Ask an admin to assign at least one.');
        }
        return view('orders-new.create', [
            'stores'    => $stores,
            'districts' => $this->districts(),
        ]);
    }

    /** Bangladesh district → areas reference (same data the storefront uses). */
    private function districts(): array
    {
        $path = resource_path('data/bd_districts_areas.json');
        return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }

    /**
     * AJAX: delivery charge fetched FROM THE STORE WEBSITE (its zone calculator).
     * The OMS never invents a charge — it asks the website.
     */
    public function shippingQuote(Request $r)
    {
        $data = $r->validate([
            'store_id' => 'required|exists:stores,id',
            'zone'     => 'required|in:inside_dhaka,outside_dhaka',
            'subtotal' => 'nullable|numeric|min:0',
        ]);
        if (! $r->user()->canAccessStore((int) $data['store_id'])) abort(403);

        $store = Store::find($data['store_id']);
        try {
            $resp = (new \App\Services\Storefront\StorefrontClient($store))
                ->shipping()->quote($data['zone'], (float) ($data['subtotal'] ?? 0));
            return response()->json(['ok' => true, 'shipping_total' => $resp['data']['shipping_total'] ?? null]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 200);
        }
    }

    public function store(Request $r)
    {
        $u = $r->user();
        // Same field rules as the storefront checkout (PlaceOrderRequest).
        $data = $r->validate([
            'store_id'              => 'required|exists:stores,id',
            'customer_name'         => 'required|string|max:255',
            // Canonical BD mobile: 11 digits starting 01[3-9] — matches checkout.
            'customer_phone'        => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
            'shipping_address_line' => 'required|string|max:500',
            'shipping_district'     => 'required|string|max:255',
            'shipping_area'         => 'nullable|string|max:255',
            'shipping_city'         => 'nullable|string|max:255',
            'shipping_zone'         => 'required|in:inside_dhaka,outside_dhaka',
            'payment_method'        => 'required|in:cod,bkash,nagad,rocket,upay,eps,shurjopay,amarpay,sslcommerz,bank_transfer',
            'discount'              => 'nullable|numeric|min:0',
            'coupon_code'           => 'nullable|string|max:60',
            'notes'                 => 'nullable|string|max:1000',
            'items'                 => 'required|array|min:1',
            'items.*.sku'           => 'required|string|max:255',
            'items.*.qty'           => 'required|integer|min:1',
            'items.*.unit_price'    => 'nullable|numeric|min:0',
        ], [
            'customer_phone.regex' => 'Enter a valid 11-digit Bangladeshi mobile number (e.g. 01712345678).',
        ]);

        if (! $u->canAccessStore((int) $data['store_id'])) {
            abort(403, 'You are not assigned to this store.');
        }

        $store  = Store::find($data['store_id']);
        $client = new \App\Services\Storefront\StorefrontClient($store);
        $city   = $data['shipping_city'] ?: $data['shipping_district']; // BD: city ≈ district

        // ── Delivery charge comes FROM THE WEBSITE (its zone calculator) ──
        $estSubtotal = collect($data['items'])->sum(function ($line) use ($store) {
            $p = ProductMirror::where('store_id', $store->id)->where('sku', $line['sku'])->first();
            return $p ? (float) ($p->sale_price ?? $p->price) * (int) $line['qty'] : 0;
        });
        $shippingTotal = 0.0;
        try {
            $q = $client->shipping()->quote($data['shipping_zone'], (float) $estSubtotal, $city);
            $shippingTotal = (float) ($q['data']['shipping_total'] ?? 0);
        } catch (\Throwable $e) {
            \Log::info('Shipping quote failed at order create', ['err' => $e->getMessage()]);
        }

        // ── Register the order ON THE STORE WEBSITE (source of truth) ──
        // The website creates the order (source = oms_manual), assigns the
        // canonical order number, deducts stock and fires its order.placed
        // webhook. We then mirror exactly what it returns — so both sides match.
        $payload = [
            'customer' => array_filter([
                'name'  => $data['customer_name'],
                'phone' => $data['customer_phone'],
            ], fn ($v) => $v !== null && $v !== ''),
            'shipping' => array_filter([
                // Checkout uses one contact — recipient defaults to the customer.
                'name'     => $data['customer_name'],
                'phone'    => $data['customer_phone'],
                'address'  => $data['shipping_address_line'],
                'area'     => $data['shipping_area'] ?? null,
                'city'     => $city,
                'district' => $data['shipping_district'],
                'zone'     => $data['shipping_zone'],
            ], fn ($v) => $v !== null && $v !== ''),
            'items' => array_map(fn ($line) => array_filter([
                'sku'        => $line['sku'],
                'quantity'   => (int) $line['qty'],
                'unit_price' => $line['unit_price'] ?? null,
            ], fn ($v) => $v !== null), $data['items']),
            'payment_method' => $data['payment_method'],
            'discount'       => (float) ($data['discount'] ?? 0),
            'coupon_code'    => $data['coupon_code'] ?? null,
            'shipping_total' => $shippingTotal,
            'source'         => 'oms_manual',
            'placed_by'      => $u->name,
            'note'           => $data['notes'] ?? null,
        ];

        try {
            $resp   = $client->orders()->create($payload);
            $remote = $resp['data'] ?? $resp;
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Could not register the order on the store website: '.$e->getMessage());
        }

        if (empty($remote['order_number'])) {
            return back()->withInput()->with('error', 'The store website did not return an order number — please retry.');
        }

        // ── 2. Mirror the canonical website order into the OMS ────────────
        $order = DB::transaction(function () use ($u, $store, $remote) {
            $order = app(\App\Services\Mirror\MirrorUpserter::class)->order($store, $remote);
            $order->update([
                'placed_by_user_id' => $u->id,
                'placed_at'         => $order->placed_at ?? now(),
            ]);

            \App\Models\AuditLog::create([
                'user_id'     => $u->id,
                'action'      => 'order.placed.oms',
                'entity_type' => OrderMirror::class,
                'entity_id'   => $order->id,
                'after_json'  => ['order_number' => $order->order_number, 'grand_total' => $order->grand_total, 'pushed_to_store' => true],
                'ip'          => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]);

            return $order;
        });

        return redirect()->route('orders-new.index')
            ->with('status', "Order {$order->order_number} registered on the store & mirrored to OMS — waiting for CS verification.");
    }

    /**
     * Owner-only delete while the order is still pending verification.
     * Admins may also delete; nobody else can.
     */
    public function destroy(Request $r, OrderMirror $order)
    {
        $u = $r->user();
        $isOwner = $order->placed_by_user_id && $order->placed_by_user_id === $u->id;
        if (! $u->isAdmin() && ! $isOwner) {
            abort(403, 'You did not place this order.');
        }
        if ($order->status !== OrderStateMachine::PENDING_VERIFICATION) {
            return back()->with('error', "Cannot delete — order is already {$order->statusLabel()}.");
        }
        \App\Models\AuditLog::create([
            'user_id'     => $u->id,
            'action'      => 'order.deleted.oms',
            'entity_type' => OrderMirror::class,
            'entity_id'   => $order->id,
            'before_json' => ['order_number' => $order->order_number, 'status' => $order->status],
            'ip'          => $r->ip(),
            'user_agent'  => $r->userAgent(),
        ]);
        $order->delete();
        return redirect()->route('orders-new.index')->with('status', 'Order deleted.');
    }

    /** AJAX SKU/name lookup for the product picker. */
    public function productSearch(Request $r)
    {
        $r->validate(['store_id' => 'required|exists:stores,id', 'q' => 'nullable|string|max:120']);
        $u = $r->user();
        if (! $u->canAccessStore((int) $r->store_id)) abort(403);

        $rows = ProductMirror::where('store_id', $r->store_id)
            ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                $x->where('sku','like',"%$t%")->orWhere('name','like',"%$t%");
            }))
            ->orderBy('name')->limit(20)->get(['sku','name','price','sale_price','stock_quantity']);
        return response()->json($rows);
    }
}
