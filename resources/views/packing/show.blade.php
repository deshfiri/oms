<x-app-layout>
    @section('title', 'Pack · '.$order->order_number)
    <div class="admin-page-header">
        <div><h1>Pack — {{ $order->order_number }}</h1><p class="sub">Capture weight, dimensions and courier — then book the shipment.</p></div>
    </div>

    <form method="POST" action="{{ route('packing.complete', $order) }}" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        @csrf
        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="package" title="Parcel"/></div>
            <div class="admin-card-body" style="display:flex;flex-direction:column;gap:10px">
                <label style="display:block">
                    <span style="font-size:12px;font-weight:600">Weight (kg) *</span>
                    <input name="weight_kg" type="number" step="0.001" min="0" required autofocus class="input" style="padding:10px">
                </label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
                    <label><span style="font-size:11px;color:var(--a-text-2)">L cm</span><input name="length_cm" type="number" step="0.1" class="input"></label>
                    <label><span style="font-size:11px;color:var(--a-text-2)">W cm</span><input name="width_cm" type="number" step="0.1" class="input"></label>
                    <label><span style="font-size:11px;color:var(--a-text-2)">H cm</span><input name="height_cm" type="number" step="0.1" class="input"></label>
                </div>
                <label>
                    <span style="font-size:12px;font-weight:600">Courier *</span>
                    <select name="courier" class="input" required>
                        <option value="steadfast">Steadfast</option>
                        <option value="pathao">Pathao</option>
                        <option value="carrybee">CarryBee</option>
                        <option value="redx">RedX</option>
                        <option value="manual">Manual</option>
                    </select>
                </label>
                <label>
                    <span style="font-size:12px;font-weight:600">Tracking / consignment *</span>
                    <input name="tracking_code" required autocomplete="off" placeholder="Required by storefront" class="input" style="font-family:ui-monospace,monospace">
                </label>
                <label>
                    <span style="font-size:12px;font-weight:600">COD amount</span>
                    <input name="cod_amount" type="number" step="0.01" value="{{ $order->payment_method==='cod' ? $order->grand_total : 0 }}" class="input">
                </label>
                <button class="btn btn-dark btn-lg btn-block" style="margin-top:6px">Book shipment & print AWB →</button>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="list" title="Items in parcel"/></div>
            <div class="admin-card-body">
                <ul style="list-style:none;margin:0;padding:0">
                    @foreach($order->items as $it)
                        <li style="padding:6px 0;border-top:1px solid var(--a-border);display:flex;justify-content:space-between;font-size:13px">
                            <span><span style="font-family:ui-monospace,monospace;color:var(--a-text-3);font-size:11px">{{ $it->sku }}</span> {{ $it->name }}</span>
                            <span style="font-weight:600">× {{ $it->qty }}</span>
                        </li>
                    @endforeach
                </ul>
                <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--a-border);font-size:12px;color:var(--a-text-2)">
                    <p style="margin:0 0 4px"><strong>{{ $order->shipping_name }}</strong> · {{ $order->shipping_phone }}</p>
                    <p style="margin:0">{{ $order->shipping_address_line }}, {{ $order->shipping_area }}, {{ $order->shipping_city }}</p>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
