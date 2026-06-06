<?php

namespace App\Http\Controllers;

use App\Models\ProductMirror;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\Store;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Http\Request;

class StockCountController extends Controller
{
    public function index()
    {
        $counts = StockCount::with('store:id,dfid,business_name,name')->latest()->paginate(25);
        return view('stock_counts.index', compact('counts'));
    }

    public function create()
    {
        $stores = Store::where('is_active', true)->orderBy('business_name')->get(['id','dfid','business_name','name']);
        return view('stock_counts.create', compact('stores'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'store_id' => 'required|exists:stores,id',
            'location' => 'nullable|string|max:120',
        ]);
        $count = StockCount::create([
            'store_id'  => $data['store_id'],
            'location'  => $data['location'] ?? null,
            'started_at'=> now(),
            'status'    => 'open',
        ]);
        return redirect()->route('stock-counts.show', $count);
    }

    public function show(StockCount $count)
    {
        $count->load('lines');
        return view('stock_counts.show', compact('count'));
    }

    public function addLine(Request $r, StockCount $count)
    {
        $data = $r->validate(['sku'=>'required|string','counted_qty'=>'required|integer|min:0','notes'=>'nullable|string']);
        $product = ProductMirror::where('store_id',$count->store_id)->where('sku',$data['sku'])->first();
        $expected = $product?->stock_quantity ?? 0;

        $line = StockCountLine::updateOrCreate(
            ['stock_count_id'=>$count->id, 'sku'=>$data['sku']],
            [
                'expected_qty'=>$expected,
                'counted_qty' =>$data['counted_qty'],
                'variance'    =>$data['counted_qty'] - $expected,
                'notes'       =>$data['notes'] ?? null,
                'counted_at'  =>now(),
                'counter_user_id'=>auth()->id(),
            ],
        );
        $count->increment('items_counted');
        return back()->with('status', "Counted {$line->sku}: {$line->counted_qty} (var {$line->variance})");
    }

    public function complete(StockCount $count)
    {
        $items = $count->lines->map(fn ($l) => ['sku'=>$l->sku, 'quantity'=>$l->counted_qty])->all();
        try {
            (new StorefrontClient($count->store))->inventory()->bulkSet($items);
            $count->update([
                'status'         => 'completed',
                'completed_at'   => now(),
                'items_adjusted' => $count->lines()->where('variance','!=',0)->count(),
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('stock-counts.index')->with('status', 'Inventory updated on storefront');
    }
}
