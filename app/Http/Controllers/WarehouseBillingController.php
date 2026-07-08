<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\WarehouseBillingRate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseBillingController extends Controller
{
    public function index(Request $r)
    {
        // Month being viewed — default to current month
        $month = $r->month ?? now()->format('Y-m');
        [$year, $mon] = explode('-', $month . '-01');
        $monthStart = Carbon::create((int)$year, (int)$mon, 1)->startOfDay();
        $monthEnd   = $monthStart->copy()->endOfMonth()->endOfDay();

        // Global default rate (store_id IS NULL)
        $globalRate = WarehouseBillingRate::whereNull('store_id')->first()
            ?? new WarehouseBillingRate([
                'storage_rate_per_item_per_month' => 0,
                'packaging_cost_per_item'         => 0,
            ]);

        // Per-store overrides, keyed by store_id
        $storeRates = WarehouseBillingRate::whereNotNull('store_id')
            ->get()->keyBy('store_id');

        $stores = Store::where('is_active', true)->orderBy('business_name')->get();

        // Build per-store billing rows
        $billing = $stores->map(function (Store $store) use ($globalRate, $storeRates, $monthStart, $monthEnd) {
            $rate = $storeRates->get($store->id) ?? $globalRate;

            // Items currently in warehouse (available + reserved — not damaged/returned)
            $itemsStored = (int) DB::table('warehouse_stock as ws')
                ->join('warehouses as wh', 'wh.id', '=', 'ws.warehouse_id')
                ->where('wh.store_id', $store->id)
                ->selectRaw('COALESCE(SUM(ws.available + ws.reserved), 0)')
                ->value('COALESCE(SUM(ws.available + ws.reserved), 0)');

            // Orders packed this month and the total item qty packed
            $packedStats = DB::table('orders_mirror as o')
                ->join('order_items_mirror as oi', 'oi.order_id', '=', 'o.id')
                ->where('o.store_id', $store->id)
                ->whereNotNull('o.packed_at')
                ->whereBetween('o.packed_at', [$monthStart, $monthEnd])
                ->selectRaw('COUNT(DISTINCT o.id) as orders_count, COALESCE(SUM(oi.qty), 0) as items_qty')
                ->first();

            $ordersPackedCount = (int) ($packedStats->orders_count ?? 0);
            $itemsPacked       = (int) ($packedStats->items_qty ?? 0);

            $storageRate      = (float) $rate->storage_rate_per_item_per_month;
            $packagingRate    = (float) $rate->packaging_cost_per_item;

            $storageCharge    = $itemsStored   * $storageRate;
            $packagingCharge  = $itemsPacked   * $packagingRate;
            $totalCharge      = $storageCharge + $packagingCharge;

            return [
                'store'               => $store,
                'items_stored'        => $itemsStored,
                'storage_rate'        => $storageRate,
                'storage_charge'      => $storageCharge,
                'orders_packed'       => $ordersPackedCount,
                'items_packed'        => $itemsPacked,
                'packaging_rate'      => $packagingRate,
                'packaging_charge'    => $packagingCharge,
                'total_charge'        => $totalCharge,
                'has_rate_override'   => $storeRates->has($store->id),
            ];
        });

        // Build last-12-months selector options
        $monthOptions = collect(range(0, 11))->map(fn($i) => now()->subMonths($i)->format('Y-m'))->values();

        return view('warehouse-billing.index', compact(
            'billing', 'globalRate', 'storeRates', 'stores', 'month', 'monthOptions'
        ));
    }

    /** Save / update the global default rate. */
    public function saveGlobalRate(Request $r)
    {
        $data = $r->validate([
            'storage_rate_per_item_per_month' => 'required|numeric|min:0',
            'packaging_cost_per_item'         => 'required|numeric|min:0',
        ]);

        WarehouseBillingRate::updateOrCreate(['store_id' => null], $data);

        return back()->with('status', 'Global billing rates updated.');
    }

    /** Save / clear a per-store rate override. */
    public function saveStoreRate(Request $r, Store $store)
    {
        $data = $r->validate([
            'storage_rate_per_item_per_month' => 'required|numeric|min:0',
            'packaging_cost_per_item'         => 'required|numeric|min:0',
        ]);

        if ((float)$data['storage_rate_per_item_per_month'] === 0.0 && (float)$data['packaging_cost_per_item'] === 0.0) {
            WarehouseBillingRate::where('store_id', $store->id)->delete();
            return back()->with('status', "Override removed for {$store->business_name} — now using global rate.");
        }

        WarehouseBillingRate::updateOrCreate(['store_id' => $store->id], $data);
        return back()->with('status', "Rate override saved for {$store->business_name}.");
    }
}
