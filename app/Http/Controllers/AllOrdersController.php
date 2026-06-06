<?php

namespace App\Http\Controllers;

use App\Models\OrderMirror;
use App\Models\Store;
use App\Services\Couriers\CourierManager;
use App\Services\Orders\OrderStateMachine as S;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "All Orders" — the master order browser with advanced filtering across the
 * entire lifecycle. Filters: full-text, multi-status, store, payment method,
 * courier, zone, source, date range. Sortable, exportable, paginated.
 */
class AllOrdersController extends Controller
{
    /** Columns the user may sort by → SQL column. */
    private const SORTS = [
        'order'    => 'order_number',
        'customer' => 'customer_name',
        'placed'   => 'placed_at',
        'total'    => 'grand_total',
        'status'   => 'status',
        'updated'  => 'updated_at',
    ];

    public function index(Request $r)
    {
        $query = $this->filtered($r);

        // Sort
        $sort = array_key_exists($r->sort, self::SORTS) ? $r->sort : 'placed';
        $dir  = $r->dir === 'asc' ? 'asc' : 'desc';
        $query->orderBy(self::SORTS[$sort], $dir);

        $perPage = in_array((int) $r->per_page, [25, 50, 100, 200], true) ? (int) $r->per_page : 50;
        $orders  = $query->with('store:id,dfid,business_name,name', 'consignments')
            ->paginate($perPage)->withQueryString();

        // Status counts across the *unfiltered-by-status* set (so chips show totals
        // that respect the other filters but not the status filter itself).
        $countQuery = $this->filtered($r, ignoreStatus: true);
        $statusCounts = (clone $countQuery)
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $totalCount = (clone $countQuery)->count();

        $stores   = Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
        $couriers = array_keys(CourierManager::ADAPTERS);
        $statuses = S::STATUSES;
        $labels   = S::LABELS;

        return view('orders.all', compact(
            'orders','statusCounts','totalCount','stores','couriers','statuses','labels','sort','dir','perPage',
        ));
    }

    public function exportCsv(Request $r): StreamedResponse
    {
        $rows = $this->filtered($r)->with('store','consignments')->limit(10000)->get();
        return response()->streamDownload(function () use ($rows) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['order_number','dfid','business','status','customer','phone','city','zone','payment','courier','tracking','subtotal','discount','shipping','grand_total','placed_at']);
            foreach ($rows as $o) {
                $c = $o->consignments->last();
                fputcsv($h, [
                    $o->order_number, $o->store->dfid ?? '', $o->store->business_name ?? '',
                    S::LABELS[$o->status] ?? $o->status,
                    $o->customer_name, $o->customer_phone, $o->shipping_city, $o->shipping_zone,
                    $o->payment_method, $c?->courier_slug, $c?->tracking_code,
                    $o->subtotal, $o->discount, $o->shipping_total, $o->grand_total,
                    optional($o->placed_at)->format('Y-m-d H:i'),
                ]);
            }
            fclose($h);
        }, 'orders-'.now()->format('Ymd-Hi').'.csv');
    }

    /** Build the filtered base query from the request. */
    private function filtered(Request $r, bool $ignoreStatus = false)
    {
        return OrderMirror::query()
            // full-text across order #, customer, shipping, tracking, consignment, SKU
            ->when($r->q, function ($q, $t) {
                $q->where(function ($x) use ($t) {
                    $x->where('order_number', 'like', "%$t%")
                      ->orWhere('customer_name', 'like', "%$t%")
                      ->orWhere('customer_phone', 'like', "%$t%")
                      ->orWhere('customer_email', 'like', "%$t%")
                      ->orWhere('shipping_name', 'like', "%$t%")
                      ->orWhere('shipping_phone', 'like', "%$t%")
                      ->orWhereHas('consignments', fn($c) => $c->where('tracking_code','like',"%$t%")->orWhere('consignment_id','like',"%$t%"))
                      ->orWhereHas('items', fn($i) => $i->where('sku','like',"%$t%")->orWhere('name','like',"%$t%"));
                });
            })
            ->when(! $ignoreStatus && $r->filled('status'), function ($q) use ($r) {
                $statuses = is_array($r->status) ? $r->status : explode(',', $r->status);
                $q->whereIn('status', array_filter($statuses));
            })
            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
            ->when($r->payment_method, fn($q,$p) => $q->where('payment_method', $p))
            ->when($r->zone, fn($q,$z) => $q->where('shipping_zone', $z))
            ->when($r->courier, fn($q,$slug) => $q->whereHas('consignments', fn($c) => $c->where('courier_slug', $slug)))
            ->when($r->source === 'oms', fn($q) => $q->whereNotNull('placed_by_user_id'))
            ->when($r->source === 'storefront', fn($q) => $q->whereNull('placed_by_user_id'))
            ->when($r->from, fn($q,$d) => $q->whereDate('placed_at', '>=', $d))
            ->when($r->to, fn($q,$d) => $q->whereDate('placed_at', '<=', $d));
    }
}
