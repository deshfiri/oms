<?php

namespace App\Http\Controllers;

use App\Models\DamageLog;
use App\Models\ProductMirror;
use App\Models\Store;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Http\Request;

class DamageController extends Controller
{
    public function index()
    {
        $items = DamageLog::with('store:id,dfid,business_name,name', 'recorder')
            ->latest()->paginate(25);
        return view('damages.index', compact('items'));
    }

    public function create()
    {
        $stores = Store::where('is_active', true)->orderBy('business_name')->get(['id','dfid','business_name','name']);
        return view('damages.create', compact('stores'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'store_id' => 'required|exists:stores,id',
            'sku'      => 'required|string',
            'qty'      => 'required|integer|min:1',
            'reason'   => 'required|string',
            'photos.*' => 'nullable|image|max:5120',
        ]);

        $store = Store::findOrFail($data['store_id']);

        $product = ProductMirror::where('store_id', $store->id)->where('sku', $data['sku'])->first();

        $photos = [];
        if ($r->hasFile('photos')) {
            foreach ($r->file('photos') as $f) {
                $photos[] = $f->store('damages', 'public');
            }
        }

        $log = DamageLog::create([
            'store_id'    => $store->id,
            'product_id'  => optional($product)->product_id,
            'sku'         => $data['sku'],
            'qty'         => $data['qty'],
            'reason'      => $data['reason'],
            'photos_json' => $photos,
            'recorded_by' => auth()->id(),
            'recorded_at' => now(),
        ]);

        try {
            // DFCOMMERCE's POST /damages contract: { product_id|sku, quantity, reason, photo_url? }
            $payload = [
                'sku'      => $log->sku,
                'quantity' => $log->qty,
                'reason'   => $log->reason,
            ];
            if ($log->product_id) {
                $payload['product_id'] = $log->product_id;
            }
            // API stores a single photo URL; if multiple were captured, send the first.
            if (! empty($photos)) {
                $payload['photo_url'] = asset('storage/'.$photos[0]);
            }
            $resp = (new StorefrontClient($store))->damages()->create($payload);
            $log->update([
                'posted_to_storefront_at' => now(),
                'storefront_damage_id'    => $resp['data']['id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('damages.index')->with('error', 'Saved locally; storefront sync failed: '.$e->getMessage());
        }

        return redirect()->route('damages.index')->with('status', 'Damage recorded');
    }
}
