@props([
    'order',                 // OrderMirror with `items` eager-loaded (OrderMirror::ITEM_COLUMNS)
    'limit' => 3,            // lines shown before "+N more"
    'href'  => null,         // "+N more" target; defaults to the order page
])
{{-- Products × quantity for one order, shown inline in order lists. Styles: .oi-* in public/css/oms.css --}}
@php $items = $order?->items ?? collect(); @endphp
<div class="oi-cell">
    @if($items->isEmpty())
        <span style="color:var(--a-text-3)">—</span>
    @else
        @php $lines = $items->count(); $pcs = $items->sum('qty'); @endphp
        <div class="oi-summary">{{ $lines }} {{ Str::plural('item', $lines) }} · {{ $pcs }} {{ Str::plural('pc', $pcs) }}</div>
        <ul class="oi-list">
            @foreach($items->take($limit) as $it)
                <li title="{{ $it->name }}{{ $it->variant_label ? ' · '.$it->variant_label : '' }} × {{ $it->qty }}">
                    <span class="oi-qty">{{ $it->qty }}×</span>
                    <span class="oi-name">{{ $it->name }}@if($it->variant_label)<small>{{ $it->variant_label }}</small>@endif</span>
                </li>
            @endforeach
        </ul>
        @if($lines > $limit)
            <a href="{{ $href ?? route('orders.show', $order) }}" class="oi-more">+{{ $lines - $limit }} more</a>
        @endif
    @endif
</div>
