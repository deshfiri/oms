@props([
    'order',                 // OrderMirror; eager-load "exchangeOriginal:id,order_number" in lists
])
{{-- Marks an exchange replacement order and links the original it replaces. --}}
@if($order->exchange_of_order_id)
    <span class="xo-badge" title="Exchange replacement{{ $order->exchangeOriginal ? ' for '.$order->exchangeOriginal->order_number : '' }}">
        EXCHANGE
        @if($order->exchangeOriginal)<span>of {{ $order->exchangeOriginal->order_number }}</span>@endif
    </span>
@endif
