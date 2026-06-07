@foreach($orders as $o)
<tr>
    <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" class="row-sel" style="accent-color:var(--a-accent)"></td>
    <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a></td>
    <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
    <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->shipping_city }}</div></td>
    <td>{{ $o->items()->sum('qty') }}</td>
    <td style="font-size:12px">{{ optional($o->processing_at)->diffForHumans() }}</td>
    <td style="text-align:right">
        <div style="display:inline-flex;gap:4px;justify-content:flex-end">
            <a href="{{ route('orders.show', $o) }}" class="btn btn-outline btn-sm">View</a>
            <button type="submit" formaction="{{ route('processing.start-packing', $o) }}" class="btn btn-dark btn-sm">Send to courier</button>
        </div>
    </td>
</tr>
@endforeach
