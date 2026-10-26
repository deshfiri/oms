@php
    $o = $order;
    $address = collect([$o->shipping_address_line, $o->shipping_area, $o->shipping_city])->filter()->implode(', ');
    $sending = $o->items->map(fn ($i) => $i->qty.'× '.$i->name.($i->variant_label ? ' ('.$i->variant_label.')' : ''))->implode(', ');
    $returning = $o->exchangeOriginal?->items->map(fn ($i) => $i->qty.'× '.$i->name.($i->variant_label ? ' ('.$i->variant_label.')' : ''))->implode(', ');
@endphp
<x-app-layout>
    @section('title', 'Book exchange · '.$o->order_number)
    <div class="admin-page-header">
        <div>
            <h1>Book exchange parcel</h1>
            <p class="sub">{{ $o->order_number }} · exchange for {{ $o->exchangeOriginal?->order_number ?? '—' }} · {{ $o->shipping_name ?: $o->customer_name }}</p>
        </div>
        <a href="{{ route('processing.index') }}" class="btn btn-outline">← Back to Processing</a>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px">
        <div style="display:flex;flex-direction:column;gap:14px">
            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="swap" title="Exchange"/></div>
                <div class="admin-card-body" style="font-size:13px;display:grid;grid-template-columns:auto 1fr;gap:6px 14px">
                    <span style="color:var(--a-text-2)">Deliver (new)</span><strong>{{ $sending ?: '—' }}</strong>
                    <span style="color:var(--a-text-2)">Collect (return)</span><strong>{{ $returning ?: '—' }}</strong>
                    <span style="color:var(--a-text-2)">Original order</span>
                    <span>@if($o->exchangeOriginal)<a href="{{ route('orders.show', $o->exchangeOriginal) }}" style="color:var(--a-accent);font-weight:600;text-decoration:none">{{ $o->exchangeOriginal->order_number }}</a>@else — @endif</span>
                </div>
            </div>

            @if($courierError)
                <div class="admin-card"><div class="admin-card-body" style="font-size:13px;color:#8a2424">
                    Could not resolve this store's courier: {{ $courierError }}
                </div></div>
            @elseif($manual)
                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="truck" title="1 · Create it in the Steadfast panel"
                        description="The Steadfast API cannot create exchange parcels — book it in the panel (Add parcel) with these values."/></div>
                    <div class="admin-card-body" style="font-size:13px;display:grid;grid-template-columns:auto 1fr;gap:6px 14px">
                        <span style="color:var(--a-text-2)">Exchange parcel</span><strong>✔ Tick “Exchange parcel”</strong>
                        <span style="color:var(--a-text-2)">Phone</span><strong style="font-family:ui-monospace,monospace">{{ $o->shipping_phone ?: $o->customer_phone }}</strong>
                        <span style="color:var(--a-text-2)">Name</span><strong>{{ $o->shipping_name ?: $o->customer_name }}</strong>
                        <span style="color:var(--a-text-2)">Address</span><strong>{{ $address }}</strong>
                        <span style="color:var(--a-text-2)">Cash on delivery</span><strong>৳{{ number_format($cod, 0) }}</strong>
                        <span style="color:var(--a-text-2)">Invoice / order ID</span><strong style="font-family:ui-monospace,monospace">{{ $o->order_number }}</strong>
                        <span style="color:var(--a-text-2)">Item / note</span><strong>Deliver: {{ $sending }}@if($returning) · Collect: {{ $returning }}@endif</strong>
                        @if($o->courier_note)
                            <span style="color:var(--a-text-2)">Courier note</span><strong style="white-space:pre-line">{{ $o->courier_note }}</strong>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('processing.exchange-booking.store', $o) }}" class="admin-card">
                    @csrf
                    <div class="admin-card-head"><x-admin.section-head icon="check" title="2 · Enter the consignment"
                        description="The OMS checks the ID with Steadfast before saving it — then label, packing and tracking work as usual."/></div>
                    <div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <label><span style="font-size:12px;font-weight:600">Consignment ID *</span>
                            <input class="input" name="consignment_id" value="{{ old('consignment_id') }}" required maxlength="64"
                                pattern="[A-Za-z0-9_\-]+" inputmode="numeric" placeholder="e.g. 308619779" autocomplete="off"></label>
                        <label><span style="font-size:12px;font-weight:600">Tracking code</span>
                            <input class="input" name="tracking_code" value="{{ old('tracking_code') }}" maxlength="64"
                                pattern="[A-Za-z0-9_\-]+" placeholder="optional" autocomplete="off"></label>
                        <button class="btn btn-dark" style="grid-column:1/-1">Verify with Steadfast & save</button>
                    </div>
                </form>
            @else
                <form method="POST" action="{{ route('processing.start-packing', $o) }}" class="admin-card">
                    @csrf
                    <div class="admin-card-head"><x-admin.section-head icon="truck" title="Send to courier"
                        description="This store ships with {{ ucfirst($slug) }} — the exchange replacement is booked like any order."/></div>
                    <div class="admin-card-body"><button class="btn btn-dark">Send to courier</button></div>
                </form>
            @endif
        </div>

        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="user" title="Ship to"/></div>
            <div class="admin-card-body" style="font-size:13px">
                <p style="font-weight:600;margin:0">{{ $o->shipping_name ?: $o->customer_name }}</p>
                <p style="color:var(--a-text-2);margin:2px 0 0">{{ $o->shipping_phone ?: $o->customer_phone }}</p>
                <p style="color:var(--a-text-2);margin:6px 0 0">{{ $address }}</p>
                <p style="color:var(--a-text-2);margin:6px 0 0">Payment: {{ strtoupper($o->payment_method ?? '—') }} · Total ৳{{ number_format($o->grand_total, 0) }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
