<x-app-layout>
    @section('title', 'Inbox')

    <div class="admin-page-header">
        <div><h1>Inbox</h1><p class="sub">{{ $orders->total() }} order(s) across all merchants matching current filters.</p></div>
    </div>

    <div class="admin-card">
        <div class="admin-card-head">
            <x-admin.section-head icon="inbox" title="Incoming orders" description="Click a row to open. Use Accept & Pick to spawn a picking session."/>
            <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Order # · name · phone" class="input" style="width:240px;padding:7px 12px;font-size:13px">
                <select name="store_id" class="input" style="width:auto;padding:7px 10px;font-size:13px" onchange="this.form.submit()">
                    <option value="">All stores</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" @selected(request('store_id')==$s->id)>{{ ($s->dfid ? $s->dfid.' · ' : '').($s->business_name ?? $s->name) }}</option>
                    @endforeach
                </select>
                <select name="status" class="input" style="width:auto;padding:7px 10px;font-size:13px" onchange="this.form.submit()">
                    <option value="">Pending / Confirmed</option>
                    @foreach(\App\Models\OrderMirror::STATUSES as $s)
                        <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <button class="btn btn-dark">Apply</button>
                @if(request()->hasAny(['q','status','store_id','payment']))
                    <a href="{{ route('inbox.index') }}" class="btn btn-ghost">Clear</a>
                @endif
            </form>
        </div>

        @if($orders->isEmpty())
            <x-admin.empty icon="inbox" title="Inbox empty" description="No orders match these filters. New orders arrive automatically via webhook." />
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Store</th>
                            <th>Customer</th>
                            <th>Placed</th>
                            <th style="text-align:right">Total</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($orders as $o)
                        <tr>
                            <td><a href="{{ route('orders.show', $o) }}" style="font-weight:700;color:var(--a-accent);text-decoration:none">{{ $o->order_number }}</a></td>
                            <td style="font-size:12px">
                                <strong>{{ $o->store->dfid ?? '—' }}</strong>
                                <div style="font-size:11px;color:var(--a-text-3)">{{ $o->store->business_name ?? $o->store->name ?? '' }}</div>
                            </td>
                            <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div></td>
                            <td style="white-space:nowrap">{{ optional($o->placed_at)->format('d M Y') ?? '—' }}<div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->placed_at)->format('H:i') }}</div></td>
                            <td style="text-align:right;font-weight:600">৳{{ number_format($o->grand_total) }}</td>
                            <td><x-admin.pill :status="$o->status" :label="$o->statusLabel()"/></td>
                            <td style="text-align:right">
                                <a href="{{ route('orders.show', $o) }}" class="btn btn-dark btn-sm">Open</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $orders->links() }}</div>
        @endif
    </div>
    <x-live-refresh scope="verification"/>
</x-app-layout>
