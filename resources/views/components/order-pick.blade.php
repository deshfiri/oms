@props([
    'order',                 // OrderMirror with `items` eager-loaded (OrderMirror::ITEM_COLUMNS)
])
{{-- Row checkbox for order lists. Carries the order's products ([name, variant, qty])
     so <x-order-pick-bar> (in the layout) can total and list whatever is ticked. --}}
@php $items = $order->items ?? collect(); @endphp
<input type="checkbox" value="{{ $order->id }}" data-order-pick
       data-lines="{{ $items->count() }}" data-pcs="{{ $items->sum('qty') }}"
       data-items="{{ $items->map(fn ($i) => [$i->name, $i->variant_label, (int) $i->qty])->values()->toJson() }}"
       aria-label="Select order {{ $order->order_number }}"
       {{ $attributes->merge(['style' => 'accent-color:var(--a-accent)']) }}>
