@foreach($orders as $courier => $rows)
<div class="admin-card" style="margin-bottom:14px">
    <div class="admin-card-head">
        <x-admin.section-head icon="truck" :title="strtoupper($courier)" :description="$rows->count().' parcel(s)'"/>
        <a href="{{ route('dispatch.csv', ['courier'=>$courier]) }}" class="btn btn-outline btn-sm">CSV</a>
    </div>
    <div class="atable-wrap">
        <table class="atable">
            <thead><tr>
                <th style="width:36px"><input type="checkbox" class="disp-group-all" data-courier="{{ $courier }}" style="accent-color:var(--a-accent)"></th>
                <th>Order</th><th>Store</th><th>Consignment ID</th><th>Tracking</th><th>Recipient</th><th style="text-align:right">COD</th><th></th>
            </tr></thead>
            <tbody>
            @foreach($rows as $o)
                @php
                    $c    = $o->consignments->last();
                    $caps = $courierCaps[$c?->courier_slug ?? $courier] ?? ['provides_tracking'=>true];
                    $hasCid = $c && $c->consignment_id;
                @endphp
                <tr>
                    <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" class="disp-sel" data-courier="{{ $courier }}" style="accent-color:var(--a-accent)"></td>
                    <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:600;text-decoration:none">{{ $o->order_number }}</a></td>
                    <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
                    <td style="font-family:ui-monospace,monospace;font-size:12.5px;font-weight:700">
                        @if($hasCid) {{ $c->consignment_id }}
                        @else <span title="Waiting for the courier API to return the consignment ID" style="display:inline-block;font-family:'Poppins',sans-serif;font-size:10px;font-weight:700;color:#92400e;background:#fef3c7;border-radius:4px;padding:1px 6px">PENDING API</span>
                        @endif
                    </td>
                    <td style="font-family:ui-monospace,monospace;font-size:11px;color:var(--a-text-3)">
                        @if($c && $c->tracking_code) {{ $c->tracking_code }}
                        @elseif(! ($caps['provides_tracking'] ?? true)) <span style="font-family:'Poppins',sans-serif;font-size:10px;color:var(--a-text-3)">no tracking</span>
                        @else —
                        @endif
                    </td>
                    <td>{{ $o->shipping_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->shipping_city }}</div></td>
                    <td style="text-align:right;font-weight:600">৳{{ number_format($o->payment_method==='cod' ? $o->grand_total : 0, 2) }}</td>
                    <td style="text-align:right;white-space:nowrap">
                        <div style="display:inline-flex;gap:4px">
                            <a href="{{ route('labels.single', $o) }}" target="_blank" class="btn btn-outline btn-sm js-label-link">Label</a>
                            <button type="submit" formaction="{{ route('dispatch.handover', $o) }}" class="btn btn-dark btn-sm">Handed over</button>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach
