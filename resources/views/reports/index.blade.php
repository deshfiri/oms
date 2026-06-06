<x-app-layout>
    @section('title', 'Reports')
    <div class="admin-page-header"><div><h1>Reports</h1><p class="sub">Roll-ups across orders, shipments, returns and damages.</p></div></div>

    <form method="GET" class="admin-card" style="margin-bottom:14px">
        <div class="admin-card-body" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
            <label><span style="font-size:11px;color:var(--a-text-2)">From</span><input name="from" type="date" value="{{ $from->format('Y-m-d') }}" class="input"></label>
            <label><span style="font-size:11px;color:var(--a-text-2)">To</span><input name="to" type="date" value="{{ $to->format('Y-m-d') }}" class="input"></label>
            <label><span style="font-size:11px;color:var(--a-text-2)">Store</span>
                <select name="store_id" class="input" style="padding:7px 10px">
                    <option value="">All stores</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" @selected($sid==$s->id)>{{ ($s->dfid ? $s->dfid.' · ' : '').($s->business_name ?? $s->name) }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn btn-dark">Run</button>
        </div>
    </form>

    <x-admin.stat-strip :items="[
        ['label'=>'Orders',     'value'=>number_format($totals['orders']),                  'sub'=>'In range'],
        ['label'=>'GMV',        'value'=>'৳'.number_format($totals['gmv'], 0),              'sub'=>'Gross merchandise', 'tone'=>'success'],
        ['label'=>'Delivered',  'value'=>number_format($totals['delivered']),               'sub'=>'Reached customer'],
        ['label'=>'Cancelled',  'value'=>number_format($totals['cancelled']),               'sub'=>'Voided',           'tone'=>$totals['cancelled']>0?'warning':'default'],
        ['label'=>'Returns',    'value'=>number_format($totals['returned']),                'sub'=>'RMAs raised',       'tone'=>$totals['returned']>0?'danger':'default'],
        ['label'=>'Damaged units','value'=>number_format($totals['damaged_qty']),           'sub'=>'Logged'],
        ['label'=>'Shipments',  'value'=>number_format($totals['shipments']),               'sub'=>'Booked'],
        ['label'=>'Avg pick',   'value'=>number_format($totals['avg_pick_min'] ?? 0, 1).'m','sub'=>'Pick-to-complete'],
    ]" />

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:14px">
        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="cart" title="By store" description="GMV split for this range."/></div>
            @if($byStore->isEmpty())
                <x-admin.empty icon="cart" title="No orders in range"/>
            @else
                <div class="atable-wrap">
                    <table class="atable">
                        <thead><tr><th>Store</th><th>Orders</th><th style="text-align:right">GMV</th></tr></thead>
                        <tbody>
                            @foreach($byStore as $row)
                                <tr>
                                    <td><strong>{{ optional($row->store)->dfid ?? '—' }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($row->store)->business_name ?? '' }}</div></td>
                                    <td>{{ $row->orders }}</td>
                                    <td style="text-align:right;font-weight:600">৳{{ number_format($row->gmv, 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="truck" title="Courier performance"/></div>
            @if($byCourier->isEmpty())
                <x-admin.empty icon="truck" title="No shipments in range"/>
            @else
                <div class="atable-wrap">
                    <table class="atable">
                        <thead><tr><th>Courier</th><th>Parcels</th><th>Delivered</th><th>Rate</th></tr></thead>
                        <tbody>
                        @foreach($byCourier as $c)
                            <tr>
                                <td>{{ strtoupper($c->courier) }}</td>
                                <td>{{ $c->parcels }}</td>
                                <td>{{ $c->delivered }}</td>
                                <td>{{ $c->parcels ? number_format(100*$c->delivered/$c->parcels, 1) : 0 }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
