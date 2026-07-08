<?php

namespace App\Http\Controllers;

use App\Models\ProductMirror;
use App\Models\Store;
use App\Models\WarehouseProductCost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseCostController extends Controller
{
    public function index(Request $r)
    {
        $storeId = $r->store_id;

        // Paginated products filtered by store / search
        $products = ProductMirror::with('store:id,dfid,business_name,name')
            ->when($storeId, fn($q, $id) => $q->where('store_id', $id))
            ->when($r->q, fn($q, $t) => $q->where(function ($x) use ($t) {
                $x->where('sku', 'like', "%$t%")->orWhere('name', 'like', "%$t%");
            }))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        // Available qty per (store_id, sku) for products on this page
        $pageSkus      = $products->pluck('sku')->unique()->values()->toArray();
        $pageStoreIds  = $products->pluck('store_id')->unique()->values()->toArray();

        $stockMap = DB::table('warehouse_stock as ws')
            ->join('warehouses as wh', 'wh.id', '=', 'ws.warehouse_id')
            ->whereIn('ws.sku', $pageSkus)
            ->whereIn('wh.store_id', $pageStoreIds)
            ->selectRaw('ws.sku, wh.store_id, SUM(ws.available) as total_available')
            ->groupBy('ws.sku', 'wh.store_id')
            ->get()
            ->keyBy(fn($row) => $row->store_id . '_' . $row->sku);

        // Saved cost settings for products on this page
        $costsMap = WarehouseProductCost::whereIn('sku', $pageSkus)
            ->whereIn('store_id', $pageStoreIds)
            ->get()
            ->keyBy(fn($c) => $c->store_id . '_' . $c->sku);

        // Aggregate summary across ALL products that have cost data
        $summary = DB::table('warehouse_product_costs as wpc')
            ->join('warehouses as wh', 'wh.store_id', '=', 'wpc.store_id')
            ->join('warehouse_stock as ws', function ($j) {
                $j->on('ws.sku', '=', 'wpc.sku')
                  ->on('ws.warehouse_id', '=', 'wh.id');
            })
            ->when($storeId, fn($q) => $q->where('wpc.store_id', $storeId))
            ->selectRaw('
                SUM(ws.available * wpc.cost_price)          AS total_stock_value,
                SUM(ws.available * wpc.daily_storage_rate)  AS total_daily_cost,
                COUNT(DISTINCT wpc.sku)                     AS products_with_cost
            ')
            ->first();

        $stores = Store::orderBy('business_name')->get(['id', 'dfid', 'business_name', 'name']);

        return view('warehouse-costs.index', compact(
            'products', 'stockMap', 'costsMap', 'summary', 'stores'
        ));
    }

    public function save(Request $r)
    {
        $data = $r->validate([
            'costs'                      => 'required|array|min:1',
            'costs.*.store_id'           => 'required|integer|exists:stores,id',
            'costs.*.sku'                => 'required|string|max:200',
            'costs.*.cost_price'         => 'required|numeric|min:0',
            'costs.*.daily_storage_rate' => 'required|numeric|min:0',
            'costs.*.notes'              => 'nullable|string|max:500',
        ]);

        foreach ($data['costs'] as $item) {
            WarehouseProductCost::updateOrCreate(
                ['store_id' => $item['store_id'], 'sku' => $item['sku']],
                [
                    'cost_price'         => $item['cost_price'],
                    'daily_storage_rate' => $item['daily_storage_rate'],
                    'notes'              => $item['notes'] ?? null,
                ]
            );
        }

        return back()->with('status', count($data['costs']) . ' product cost(s) saved.');
    }
}
