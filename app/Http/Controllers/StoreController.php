<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
    public function index() { return view('stores.index', ['stores' => Store::orderBy('name')->get()]); }

    public function create() { return view('stores.form', ['store' => new Store()]); }

    public function store(Request $r)
    {
        $data   = $this->validateForm($r);
        $secret = $data['api_secret'];
        unset($data['api_secret']);
        $store                  = new Store($data);
        $store->api_secret      = $secret;
        $store->license_api_key = Str::random(48);
        $store->save();
        return redirect()->route('stores.index')->with('status', 'Store added');
    }

    public function edit(Store $store)
    {
        $store->load(['otpCodes' => fn ($q) => $q->latest()->limit(20)]);
        return view('stores.form', compact('store'));
    }

    public function update(Request $r, Store $store)
    {
        $data   = $this->validateForm($r, $store);
        $secret = $data['api_secret'] ?? null;
        unset($data['api_secret']);
        $store->fill($data);
        if (! empty($secret)) {
            $store->api_secret = $secret;
        }
        $store->save();
        return redirect()->route('stores.index')->with('status', 'Store updated');
    }

    public function destroy(Store $store) { $store->delete(); return back()->with('status', 'Store deleted'); }

    public function ping(Store $store)
    {
        $r = (new StorefrontClient($store))->pingDetailed();
        if ($r['ok']) {
            return back()->with('status', "Ping OK: {$store->name}");
        }
        $detail = $r['status'] ? "HTTP {$r['status']}: {$r['message']}" : $r['message'];
        return back()->with('error', "Ping FAILED for {$store->name} → {$detail}");
    }

    public function syncNow(Store $store)
    {
        \App\Jobs\SyncOrdersJob::dispatchSync($store->id);
        \App\Jobs\SyncPendingConsignmentsJob::dispatchSync($store->id);
        \App\Jobs\SyncProductsJob::dispatchSync($store->id);
        \App\Jobs\SyncShipmentsJob::dispatchSync($store->id);
        \App\Jobs\SyncReturnsJob::dispatchSync($store->id);
        return back()->with('status', "Sync ran for {$store->name}");
    }

    public function autoSync(Request $r)
    {
        $throttleSeconds = 10;
        $last = \Illuminate\Support\Facades\Cache::get('oms:autosync:last');
        if ($last && now()->diffInSeconds(\Carbon\Carbon::parse($last)) < $throttleSeconds) {
            return response()->json(['ok' => true, 'skipped' => 'throttled']);
        }
        \Illuminate\Support\Facades\Cache::put('oms:autosync:last', now()->toIso8601String(), 120);

        $newOrders = 0;
        foreach (\App\Models\Store::where('is_active', true)->get() as $store) {
            try {
                $before = \App\Models\OrderMirror::where('store_id', $store->id)->count();
                \App\Jobs\SyncOrdersJob::dispatchSync($store->id);
                \App\Jobs\SyncPendingConsignmentsJob::dispatchSync($store->id);
                \App\Jobs\SyncShipmentsJob::dispatchSync($store->id);
                $newOrders += max(0, \App\Models\OrderMirror::where('store_id', $store->id)->count() - $before);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('autoSync failed', ['store' => $store->id, 'err' => $e->getMessage()]);
            }
        }
        return response()->json(['ok' => true, 'new_orders' => $newOrders]);
    }

    protected function validateForm(Request $r, ?Store $store = null): array
    {
        $rules = [
            'dfid'           => ['required', 'string', 'max:60', Rule::unique('stores', 'dfid')->ignore($store?->id)],
            'business_name'  => 'required|string|max:160',
            'domain_name'    => 'nullable|string|max:160',
            'customer_name'  => 'required|string|max:120',
            'customer_phone' => 'required|string|max:40',
            'base_url'       => 'required|url',
            'api_key'        => 'required|string|max:120',
            'api_secret'     => $store ? 'nullable|string' : 'required|string',
            'webhook_secret' => 'nullable|string',
            'is_active'      => 'nullable|boolean',
        ];

        $data = $r->validate($rules);
        $data['is_active']      = (bool) $r->input('is_active', true);
        $data['webhook_secret'] = $data['webhook_secret'] ?? Str::random(48);
        $data['name']           = trim(($data['dfid'] ?? '') . ' · ' . $data['business_name']);

        $base     = rtrim($data['base_url'], '/');
        $suffixes = [
            '/api/v1', '/api/v2', '/api/v3',
            '/api/documentation', '/api/docs', '/api-docs',
            '/docs/api', '/docs',
            '/swagger-ui', '/swagger',
            '/openapi.json', '/openapi.yaml', '/openapi',
            '/api',
        ];
        do {
            $changed = false;
            foreach ($suffixes as $s) {
                if (str_ends_with($base, $s)) {
                    $base    = substr($base, 0, -strlen($s));
                    $base    = rtrim($base, '/');
                    $changed = true;
                }
            }
        } while ($changed);
        $data['base_url'] = $base;

        return $data;
    }
}
