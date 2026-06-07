@foreach($orders as $o)
@php $c = $o->consignments->last(); @endphp
<tr>
    <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" class="pack-sel" style="accent-color:var(--a-accent)"></td>
    <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a></td>
    <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
    <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->shipping_city }}</div></td>
    <td style="font-size:12px"><strong>{{ strtoupper($c?->courier_slug ?? '—') }}</strong></td>
    <td style="font-family:ui-monospace,monospace;font-size:12.5px;font-weight:700">
        @if($c?->consignment_id)
            {{ $c->consignment_id }}
        @else
            <span title="Waiting for the courier API to return the consignment ID" style="display:inline-block;font-family:'Poppins',sans-serif;font-size:10px;font-weight:700;color:#92400e;background:#fef3c7;border-radius:4px;padding:1px 6px">PENDING API</span>
        @endif
    </td>
    <td style="font-family:ui-monospace,monospace;font-size:11px;color:var(--a-text-3)">{{ $c?->tracking_code ?? '—' }}</td>
    <td style="font-size:12px">{{ optional($c?->booked_at)->diffForHumans() ?? '—' }}</td>
    <td style="text-align:right;white-space:nowrap">
        <div style="display:inline-flex;gap:4px">
            <a href="{{ route('labels.single', $o) }}" target="_blank" class="btn btn-outline btn-sm js-label-link">Print label</a>
            <button type="submit" formaction="{{ route('packing.mark-packed', $o) }}" class="btn btn-dark btn-sm">Mark Packed</button>
        </div>
    </td>
</tr>
@endforeach
