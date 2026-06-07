@php
    use App\Services\Orders\OrderStateMachine as S;
    $qs = fn(array $over = []) => '?' . http_build_query(array_merge(request()->except('page'), $over));
    $activeStatuses = collect(is_array(request('status')) ? request('status') : array_filter(explode(',', (string) request('status'))));
@endphp

{{-- Status chips --}}
<div class="admin-card" style="margin-bottom:14px">
    <div class="admin-card-body" style="display:flex;flex-wrap:wrap;gap:6px">
        <a href="{{ route('orders.index') . $qs(['status' => null]) }}"
           class="pill {{ $activeStatuses->isEmpty() ? 'pill-active' : 'pill-default' }}"
           style="text-decoration:none;cursor:pointer">All <strong>{{ number_format($totalCount) }}</strong></a>
        @foreach($statuses as $st)
            @php $n = $statusCounts[$st] ?? 0; @endphp
            <a href="{{ route('orders.index') . $qs(['status' => $st]) }}"
               class="pill pill-{{ $st }} {{ $activeStatuses->contains($st) ? 'pill-active' : '' }}"
               style="text-decoration:none;cursor:pointer;{{ $n === 0 ? 'opacity:.45' : '' }}">
                {{ $labels[$st] }} <strong>{{ $n }}</strong>
            </a>
        @endforeach
    </div>
</div>

{{-- Results table --}}
<div class="admin-card">
    <div class="admin-card-head">
        <x-admin.section-head icon="cart" title="Orders" :description="number_format($orders->total()).' result(s)'"/>
        <form method="GET" action="{{ route('orders.index') }}" style="display:flex;gap:6px;align-items:center">
            @foreach(request()->except(['per_page','page']) as $k => $v)
                @if(is_array($v)) @foreach($v as $vv)<input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">@endforeach
                @else <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
            @endforeach
            <span style="font-size:11px;color:var(--a-text-2)">Per page</span>
            <select class="input" name="per_page" style="width:auto" onchange="this.form.submit()">
                @foreach([25,50,100,200] as $pp)<option value="{{ $pp }}" @selected($perPage===$pp)>{{ $pp }}</option>@endforeach
            </select>
        </form>
    </div>

    @if($orders->isEmpty())
        <x-admin.empty icon="search" title="No orders match" description="Adjust or clear the filters above."/>
    @else
        @php
            $th = function($key, $label, $align = 'left') use ($sort, $dir, $qs) {
                $next = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
                $arrow = $sort === $key ? ($dir === 'asc' ? '▲' : '▼') : '';
                return '<th style="text-align:'.$align.'"><a href="'.route('orders.index').$qs(['sort'=>$key,'dir'=>$next]).'" style="text-decoration:none;color:inherit;white-space:nowrap">'.$label.' <span style="color:var(--a-accent);font-size:9px">'.$arrow.'</span></a></th>';
            };
        @endphp
        <div class="atable-wrap">
            <table class="atable">
                <thead><tr>
                    {!! $th('order','Order') !!}
                    <th>Store</th>
                    {!! $th('customer','Customer') !!}
                    <th>Courier / Consignment</th>
                    {!! $th('placed','Placed') !!}
                    {!! $th('total','Total','right') !!}
                    {!! $th('status','Status') !!}
                    <th></th>
                </tr></thead>
                <tbody>
                    @foreach($orders as $o)
                        @php $c = $o->consignments->last(); @endphp
                        <tr>
                            <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a>
                                @if($o->placed_by_user_id)<div style="font-size:10px;color:var(--a-text-3)">OMS-placed</div>@endif</td>
                            <td style="font-size:12px"><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
                            <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div></td>
                            <td style="font-size:12px">
                                @if($c)<strong>{{ strtoupper($c->courier_slug) }}</strong><div style="font-family:ui-monospace,monospace;font-size:11.5px;font-weight:600">{{ $c->consignment_id ?? '—' }}</div>@if($c->tracking_code)<div style="font-family:ui-monospace,monospace;font-size:10px;color:var(--a-text-3)">{{ $c->tracking_code }}</div>@endif @else <span style="color:var(--a-text-3)">—</span>@endif
                            </td>
                            <td style="white-space:nowrap;font-size:12px">{{ optional($o->placed_at)->format('d M Y') ?? '—' }}<div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->placed_at)->format('H:i') }}</div></td>
                            <td style="text-align:right;font-weight:600">৳{{ number_format($o->grand_total) }}</td>
                            <td><x-admin.pill :status="$o->status" :label="$o->statusLabel()"/></td>
                            <td style="text-align:right"><a href="{{ route('orders.show', $o) }}" class="btn btn-outline btn-sm">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="admin-pagination">{{ $orders->links() }}</div>
    @endif
</div>
