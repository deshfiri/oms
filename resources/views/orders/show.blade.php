@php
    $sm = app(\App\Services\Orders\OrderStateMachine::class);
    $allowed = $sm->allowedNext($order->status);
@endphp
<x-app-layout>
    @section('title', 'Order '.$order->order_number)

    <div class="admin-page-header">
        <div>
            <h1>Order {{ $order->order_number }}</h1>
            <p class="sub">Placed {{ optional($order->placed_at)->diffForHumans() }} · <x-admin.pill :status="$order->status"/> · {{ ucfirst($order->payment_method ?? '—') }}</p>
        </div>
        <a href="{{ route('inbox.index') }}" class="btn btn-ghost btn-sm">← Back to inbox</a>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px">
        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="cart" title="Items" description="{{ $order->items->count() }} line item(s)"/></div>
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>SKU</th><th>Name</th><th style="text-align:right">Qty</th><th style="text-align:right">Price</th><th style="text-align:right">Total</th></tr></thead>
                    <tbody>
                    @foreach($order->items as $it)
                        <tr>
                            <td style="font-family:ui-monospace,monospace;font-size:12px">{{ $it->sku }}</td>
                            <td>{{ $it->name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $it->variant_label }}</div></td>
                            <td style="text-align:right">{{ $it->qty }}</td>
                            <td style="text-align:right">৳{{ number_format($it->unit_price, 2) }}</td>
                            <td style="text-align:right;font-weight:600">৳{{ number_format($it->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="4" style="text-align:right;color:var(--a-text-2)">Subtotal</td><td style="text-align:right">৳{{ number_format($order->subtotal, 2) }}</td></tr>
                        <tr><td colspan="4" style="text-align:right;color:var(--a-text-2)">Discount</td><td style="text-align:right">−৳{{ number_format($order->discount, 2) }}</td></tr>
                        <tr><td colspan="4" style="text-align:right;color:var(--a-text-2)">Shipping</td><td style="text-align:right">৳{{ number_format($order->shipping_total, 2) }}</td></tr>
                        <tr><td colspan="4" style="text-align:right;font-weight:700">Grand total</td><td style="text-align:right;font-weight:700;font-size:15px">৳{{ number_format($order->grand_total, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:14px">
            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="user" title="Customer"/></div>
                <div class="admin-card-body">
                    <p style="font-weight:600;margin:0">{{ $order->customer_name }}</p>
                    <p style="font-size:12px;color:var(--a-text-2);margin:2px 0 0">{{ $order->customer_phone }}</p>
                    <p style="font-size:12px;color:var(--a-text-2);margin:2px 0 0">{{ $order->customer_email }}</p>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="truck" title="Ship to"/></div>
                <div class="admin-card-body">
                    <p style="font-weight:600;margin:0">{{ $order->shipping_name }}</p>
                    <p style="font-size:12px;color:var(--a-text-2);margin:2px 0 0">{{ $order->shipping_phone }}</p>
                    <p style="font-size:12px;color:var(--a-text-2);margin:6px 0 0">{{ $order->shipping_address_line }}</p>
                    <p style="font-size:12px;color:var(--a-text-2);margin:2px 0 0">{{ $order->shipping_area }}, {{ $order->shipping_city }} · <em>{{ $order->shipping_zone }}</em></p>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="list" title="Workflow"/></div>
                <div class="admin-card-body" style="font-size:13px">
                    <p style="margin:0 0 4px">Picking sessions: <strong>{{ $order->pickingSessions->count() }}</strong></p>
                    <p style="margin:0 0 4px">Packing sessions: <strong>{{ $order->packingSessions->count() }}</strong></p>
                    <p style="margin:0 0 4px">Shipments: <strong>{{ $order->shipments->count() }}</strong></p>
                    <p style="margin:0">RMAs: <strong>{{ $order->rmas->count() }}</strong></p>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="settings" title="Change status" description="Same rules as the DFCOMMERCE admin state machine."/></div>
                <div class="admin-card-body">
                    @if(empty($allowed))
                        <p style="font-size:13px;color:var(--a-text-2);margin:0">Terminal state — no further transitions allowed.</p>
                    @else
                        <form method="POST" action="{{ route('orders.status', $order) }}" style="display:flex;flex-direction:column;gap:8px">
                            @csrf
                            <select name="to" class="input" style="padding:8px 10px;font-size:13px">
                                @foreach($allowed as $next)<option value="{{ $next }}">{{ $sm->label($next) }}</option>@endforeach
                            </select>
                            <input name="note" placeholder="Optional note for the audit log" class="input" style="padding:8px 10px;font-size:13px">
                            <button class="btn btn-dark btn-sm">Update status</button>
                        </form>
                    @endif
                    @if(! in_array($order->status, ['cancelled','refunded'], true))
                        <form method="POST" action="{{ route('orders.cancel', $order) }}" style="margin-top:10px" onsubmit="return confirm('Cancel this order?')">
                            @csrf
                            <button class="btn btn-danger btn-sm btn-block" >Cancel order</button>
                        </form>
                    @endif
                </div>
            </div>

            @if(in_array($order->status, [\App\Services\Orders\OrderStateMachine::DELIVERED, \App\Services\Orders\OrderStateMachine::PARTIAL_DELIVERY], true))
            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="swap" title="Exchange" description="Send a new product; this order awaits the returned item."/></div>
                <div class="admin-card-body">
                    <a href="{{ route('exchanges.create', $order) }}" class="btn btn-dark btn-sm btn-block">Issue exchange — pick new product</a>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
