{{-- Header checkbox: ticks every <x-order-pick> in the same table (handled by <x-order-pick-bar>). --}}
<input type="checkbox" data-order-pick-all aria-label="Select all orders in this table"
       {{ $attributes->merge(['style' => 'accent-color:var(--a-accent)']) }}>
