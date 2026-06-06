<?php

namespace App\Http\Controllers;

use App\Models\OrderMirror;
use App\Models\ProductMirror;
use App\Services\Orders\OrderStateMachine as S;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $cards = [
            ['label' => 'Order Pending', 'value' => OrderMirror::where('status', S::PENDING_VERIFICATION)->count(), 'sub' => 'Awaiting confirmation', 'href' => route('verification.index'), 'tone' => 'warning'],
            ['label' => 'Processing',   'value' => OrderMirror::where('status', S::PROCESSING)->whereDoesntHave('consignments')->count(), 'sub' => 'Awaiting label',       'href' => route('processing.index')],
            ['label' => 'Packing',      'value' => OrderMirror::where('status', S::PROCESSING)->whereHas('consignments')->count(),       'sub' => 'Label printed',        'href' => route('packing.index')],
            ['label' => 'Packed',       'value' => OrderMirror::where('status', S::PACKED)->count(),              'sub' => 'Ready for dispatch',   'href' => route('dispatch.index')],
            ['label' => 'In transit',   'value' => OrderMirror::whereIn('status', [S::DISPATCHED, S::SHIPPED, S::OUT_FOR_DELIVERY])->count(), 'sub' => 'With courier',  'href' => route('tracking.index')],
            ['label' => 'Returns',      'value' => OrderMirror::whereIn('status', [S::RETURN_PENDING, S::RETURNED, S::AWAITING_RETURN_PRODUCT])->count(), 'sub' => 'Reverse logistics', 'href' => route('returns.index'), 'tone' => 'danger'],
        ];

        $recent = OrderMirror::with('store:id,dfid,business_name,name')->latest('placed_at')->limit(10)->get();
        $low    = ProductMirror::with('store:id,dfid,business_name,name')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->where('manage_stock', true)->limit(10)->get();

        return view('dashboard', ['cards' => $cards, 'recentOrders' => $recent, 'lowStock' => $low]);
    }
}
