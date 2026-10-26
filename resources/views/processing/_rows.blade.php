@foreach($orders as $o)
<tr>
    <td><x-order-pick :order="$o" name="order_ids[]" class="row-sel"/></td>
    <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a>
        <div><x-exchange-badge :order="$o"/></div></td>
    <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
    <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->shipping_city }}</div></td>
    <td><x-order-items :order="$o"/></td>
    <td style="font-size:12px">{{ optional($o->processing_at)->diffForHumans() }}</td>
    <td style="text-align:right">
        <div style="display:inline-flex;gap:4px;justify-content:flex-end">
            <a href="{{ route('orders.show', $o) }}" class="btn btn-outline btn-sm">View</a>
            @if($o->exchange_of_order_id)
                <a href="{{ route('processing.exchange-booking', $o) }}" class="btn btn-dark btn-sm">Book exchange</a>
            @else
                <button type="submit" formaction="{{ route('processing.start-packing', $o) }}" class="btn btn-dark btn-sm">Send to courier</button>
            @endif
        </div>
    </td>
</tr>
@endforeach
