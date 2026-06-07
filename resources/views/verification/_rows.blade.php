@foreach($orders as $o)
<tr>
    <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" class="row-sel" style="accent-color:var(--a-accent)"></td>
    <td><a href="{{ route('verification.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a></td>
    <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
    <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div></td>
    <td>{{ optional($o->placed_at)->diffForHumans() }}</td>
    <td style="text-align:right;font-weight:600">৳{{ number_format($o->grand_total) }}</td>
    <td style="text-align:right"><a href="{{ route('verification.show', $o) }}" class="btn btn-dark btn-sm">Open</a></td>
</tr>
@endforeach
