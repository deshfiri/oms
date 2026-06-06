<?php

namespace App\Http\Controllers;

use App\Models\DamageLog;
use App\Models\OrderMirror;
use App\Models\PickingSession;
use App\Models\RmaWorkflow;
use App\Models\ShipmentLog;
use App\Models\Store;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $r)
    {
        $from = $r->date('from') ?? now()->subDays(7);
        $to   = $r->date('to')   ?? now();
        $sid  = $r->integer('store_id') ?: null;
        $scope = fn($q) => $sid ? $q->where('store_id', $sid) : $q;

        $orders = $scope(OrderMirror::query())->whereBetween('placed_at', [$from, $to]);
        $totals = [
            'orders'        => (clone $orders)->count(),
            'gmv'           => (clone $orders)->sum('grand_total'),
            'cancelled'     => (clone $orders)->where('status','cancelled')->count(),
            'delivered'     => (clone $orders)->where('status','delivered')->count(),
            'returned'      => $scope(RmaWorkflow::query())->whereBetween('created_at', [$from, $to])->count(),
            'damaged_qty'   => $scope(DamageLog::query())->whereBetween('recorded_at', [$from, $to])->sum('qty'),
            'shipments'     => $scope(ShipmentLog::query())->whereBetween('booked_at', [$from, $to])->count(),
            'avg_pick_min'  => $scope(PickingSession::query())
                ->whereBetween('completed_at', [$from, $to])
                ->whereNotNull('completed_at')
                ->whereNotNull('started_at')
                ->get()
                ->avg(fn($s) => $s->started_at->diffInMinutes($s->completed_at)),
        ];

        $byCourier = $scope(ShipmentLog::query())
            ->whereBetween('booked_at', [$from, $to])
            ->selectRaw('courier, count(*) as parcels, sum(case when status="delivered" then 1 else 0 end) as delivered')
            ->groupBy('courier')->get();

        // Per-store breakdown so the multi-tenant report is meaningful
        $byStore = OrderMirror::query()
            ->whereBetween('placed_at', [$from, $to])
            ->selectRaw('store_id, count(*) as orders, sum(grand_total) as gmv')
            ->groupBy('store_id')
            ->with('store:id,dfid,business_name,name')
            ->get();

        $stores = Store::orderBy('business_name')->get(['id','dfid','business_name','name']);

        return view('reports.index', compact('totals','byCourier','byStore','from','to','stores','sid'));
    }
}
