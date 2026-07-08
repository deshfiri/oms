<x-app-layout>
    @section('title', 'Pack · '.$order->order_number)
    <div class="admin-page-header">
        <div>
            <h1>Pack — {{ $order->order_number }}</h1>
            <p class="sub">Enter weight &amp; dimensions — cost is estimated automatically. Then enter the tracking number and submit.</p>
        </div>
        <a href="{{ route('packing.index') }}" class="btn btn-outline">← Packing queue</a>
    </div>

    @if($order->status === \App\Services\Orders\OrderStateMachine::PACKED)
        <div class="admin-flash admin-flash-ok" style="margin-bottom:14px">This order is already marked Packed.</div>
    @endif

    <form method="POST" action="{{ route('packing.complete', $order) }}" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        @csrf

        {{-- ── LEFT: Parcel details ───────────────────────────────── --}}
        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="package" title="Parcel details"/></div>
            <div class="admin-card-body" style="display:flex;flex-direction:column;gap:10px">

                <label style="display:block">
                    <span style="font-size:12px;font-weight:600">Weight (kg) *</span>
                    <input id="cc-weight" name="weight_kg" type="number" step="0.001" min="0.001"
                           required autofocus class="input" style="padding:10px"
                           value="{{ old('weight_kg') }}">
                </label>

                <div>
                    <span style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Dimensions (cm)</span>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
                        <label><span style="font-size:11px;color:var(--a-text-2)">Length</span><input id="cc-l" name="length_cm" type="number" step="0.1" min="0" class="input" value="{{ old('length_cm') }}"></label>
                        <label><span style="font-size:11px;color:var(--a-text-2)">Width</span><input  id="cc-w" name="width_cm"  type="number" step="0.1" min="0" class="input" value="{{ old('width_cm') }}"></label>
                        <label><span style="font-size:11px;color:var(--a-text-2)">Height</span><input id="cc-h" name="height_cm" type="number" step="0.1" min="0" class="input" value="{{ old('height_cm') }}"></label>
                    </div>
                </div>

                <label>
                    <span style="font-size:12px;font-weight:600">Courier *</span>
                    <select id="cc-courier" name="courier" class="input" required>
                        <option value="steadfast" @selected(old('courier','steadfast')==='steadfast')>Steadfast</option>
                        <option value="pathao"    @selected(old('courier')==='pathao')>Pathao</option>
                        <option value="carrybee"  @selected(old('courier')==='carrybee')>CarryBee</option>
                        <option value="redx"      @selected(old('courier')==='redx')>RedX</option>
                        <option value="manual"    @selected(old('courier')==='manual')>Manual</option>
                    </select>
                </label>

                <label>
                    <span style="font-size:12px;font-weight:600">COD amount</span>
                    <input id="cc-cod" name="cod_amount" type="number" step="0.01" min="0"
                           value="{{ old('cod_amount', $order->payment_method === 'cod' ? (float)$order->grand_total : 0) }}"
                           class="input">
                </label>

                {{-- ── Auto cost calculator ─────────────────────────────── --}}
                <div id="cost-panel" style="display:none;background:var(--a-surface-2);border:1px solid var(--a-border);border-radius:6px;padding:12px 14px">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--a-text-3);margin-bottom:8px">Estimated Courier Cost</div>
                    <div style="display:flex;flex-direction:column;gap:5px;font-size:12px">
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:var(--a-text-2)">Actual weight</span>
                            <span id="cp-actual">—</span>
                        </div>
                        <div id="cp-vol-row" style="display:flex;justify-content:space-between">
                            <span style="color:var(--a-text-2)">Volumetric weight</span>
                            <span id="cp-vol">—</span>
                        </div>
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:var(--a-text-2)">Billable weight</span>
                            <span id="cp-bill" style="font-weight:600">—</span>
                        </div>
                        <div style="border-top:1px solid var(--a-border);margin:3px 0"></div>
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:var(--a-text-2)">Zone</span>
                            <span id="cp-zone">—</span>
                        </div>
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:var(--a-text-2)">Delivery charge</span>
                            <span id="cp-delivery">—</span>
                        </div>
                        <div id="cp-cod-row" style="display:flex;justify-content:space-between">
                            <span style="color:var(--a-text-2)">COD charge (1%)</span>
                            <span id="cp-cod-fee">—</span>
                        </div>
                        <div style="border-top:1px solid var(--a-border);margin:3px 0"></div>
                        <div style="display:flex;justify-content:space-between;font-weight:700;font-size:13px">
                            <span>Total est. cost</span>
                            <span id="cp-total" style="color:var(--a-accent)">—</span>
                        </div>
                    </div>
                    <p id="cp-note" style="display:none;margin:8px 0 0;font-size:11px;color:var(--a-text-3)">Rates are estimates. Verify with your courier account.</p>
                </div>

                <label>
                    <span style="font-size:12px;font-weight:600">Tracking / consignment # *</span>
                    <input name="tracking_code" required autocomplete="off"
                           placeholder="Paste or scan from courier slip"
                           class="input" style="font-family:ui-monospace,monospace"
                           value="{{ old('tracking_code') }}">
                </label>

                <button class="btn btn-dark btn-lg btn-block" style="margin-top:4px"
                    @if($order->status === \App\Services\Orders\OrderStateMachine::PACKED) disabled @endif>
                    Complete packing &amp; print label →
                </button>
            </div>
        </div>

        {{-- ── RIGHT: Order summary ───────────────────────────────── --}}
        <div style="display:flex;flex-direction:column;gap:14px">
            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="list" title="Items in parcel"/></div>
                <div class="admin-card-body">
                    <ul style="list-style:none;margin:0;padding:0">
                        @foreach($order->items as $it)
                            <li style="padding:6px 0;border-top:1px solid var(--a-border);display:flex;justify-content:space-between;align-items:center;font-size:13px">
                                <span>
                                    <span style="font-family:ui-monospace,monospace;color:var(--a-text-3);font-size:11px">{{ $it->sku }}</span>
                                    {{ $it->name }}
                                </span>
                                <span style="font-weight:600;flex-shrink:0;margin-left:8px">× {{ $it->qty }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="user" title="Delivery address"/></div>
                <div class="admin-card-body" style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                    <div style="font-weight:600">{{ $order->shipping_name }}</div>
                    <div style="color:var(--a-text-2)">{{ $order->shipping_phone }}</div>
                    <div style="color:var(--a-text-2);margin-top:2px">{{ $order->shipping_address_line }}</div>
                    <div style="color:var(--a-text-2)">{{ $order->shipping_area }}, <strong>{{ $order->shipping_city }}</strong></div>
                    @php
                        $city = strtolower(trim($order->shipping_city ?? ''));
                        $isDhaka = in_array($city, ['dhaka']);
                    @endphp
                    <div style="margin-top:6px">
                        <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;
                            background:{{ $isDhaka ? 'rgba(17,146,100,.1)' : 'rgba(59,130,246,.1)' }};
                            color:{{ $isDhaka ? 'var(--a-accent)' : '#2563eb' }}">
                            {{ $isDhaka ? 'Inside Dhaka' : 'Outside Dhaka' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="cart" title="Order totals"/></div>
                <div class="admin-card-body" style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                    <div style="display:flex;justify-content:space-between;color:var(--a-text-2)">
                        <span>Subtotal</span><span>৳{{ number_format($order->subtotal, 2) }}</span>
                    </div>
                    @if($order->discount > 0)
                    <div style="display:flex;justify-content:space-between;color:var(--a-text-2)">
                        <span>Discount</span><span>−৳{{ number_format($order->discount, 2) }}</span>
                    </div>
                    @endif
                    <div style="display:flex;justify-content:space-between;color:var(--a-text-2)">
                        <span>Shipping</span><span>৳{{ number_format($order->shipping_total, 2) }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-weight:700;border-top:1px solid var(--a-border);margin-top:4px;padding-top:6px">
                        <span>Grand total</span><span>৳{{ number_format($order->grand_total, 2) }}</span>
                    </div>
                    <div style="font-size:11px;color:var(--a-text-3);margin-top:2px">
                        Payment: {{ strtoupper($order->payment_method ?? 'N/A') }}
                        @if($order->payment_method === 'cod')
                            &nbsp;·&nbsp;<span style="color:#d97706;font-weight:600">COD</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
    <script>
    (function () {
        // Zone detected server-side from shipping_city
        const IS_DHAKA = {{ $isDhaka ? 'true' : 'false' }};

        // Courier rate tables — base slab + extra per 500 g + COD %
        // Adjust these numbers to match your actual courier contracts.
        const RATES = {
            steadfast: {
                inside:  { baseKg: 0.5, base: 70,  extraPer500g: 15 },
                outside: { baseKg: 0.5, base: 130, extraPer500g: 30 },
                codPct: 1, codMin: 15,
            },
            pathao: {
                inside:  { baseKg: 0.5, base: 60,  extraPer500g: 20 },
                outside: { baseKg: 0.5, base: 100, extraPer500g: 30 },
                codPct: 1, codMin: 0,
            },
            carrybee: {
                inside:  { baseKg: 0.5, base: 65,  extraPer500g: 20 },
                outside: { baseKg: 0.5, base: 130, extraPer500g: 30 },
                codPct: 1, codMin: 0,
            },
            redx: {
                inside:  { baseKg: 0.5, base: 65,  extraPer500g: 20 },
                outside: { baseKg: 0.5, base: 125, extraPer500g: 30 },
                codPct: 1, codMin: 0,
            },
        };

        const $ = id => document.getElementById(id);
        const taka = n => '৳' + n.toFixed(2);

        function recalc() {
            const weight  = parseFloat($('cc-weight').value)  || 0;
            const l       = parseFloat($('cc-l').value)       || 0;
            const w       = parseFloat($('cc-w').value)       || 0;
            const h       = parseFloat($('cc-h').value)       || 0;
            const courier = $('cc-courier').value;
            const cod     = parseFloat($('cc-cod').value)     || 0;
            const panel   = $('cost-panel');

            const rate = RATES[courier];
            if (!weight || !rate) { panel.style.display = 'none'; return; }

            const volWeight = (l && w && h) ? (l * w * h) / 5000 : 0;
            const billable  = Math.max(weight, volWeight);
            const zone      = IS_DHAKA ? rate.inside : rate.outside;

            // Delivery charge: base for first baseKg slab, +extraPer500g each 500g after
            let delivery = zone.base;
            if (billable > zone.baseKg) {
                delivery += Math.ceil((billable - zone.baseKg) / 0.5) * zone.extraPer500g;
            }

            const codFee = cod > 0 ? Math.max(cod * rate.codPct / 100, rate.codMin) : 0;
            const total  = delivery + codFee;

            // Update display
            $('cp-actual').textContent = weight.toFixed(3) + ' kg';

            const volRow = $('cp-vol-row');
            if (volWeight > 0) {
                $('cp-vol').textContent = volWeight.toFixed(3) + ' kg';
                volRow.style.display = '';
            } else {
                volRow.style.display = 'none';
            }

            $('cp-bill').textContent = billable.toFixed(3) + ' kg'
                + (volWeight > weight ? ' ← volumetric' : '');
            $('cp-zone').textContent  = IS_DHAKA ? 'Inside Dhaka' : 'Outside Dhaka';
            $('cp-delivery').textContent = taka(delivery);

            const codRow = $('cp-cod-row');
            if (cod > 0) {
                $('cp-cod-fee').textContent = taka(codFee);
                codRow.style.display = '';
            } else {
                codRow.style.display = 'none';
            }

            $('cp-total').textContent = taka(total);
            $('cp-note').style.display = '';
            panel.style.display = '';
        }

        ['cc-weight','cc-l','cc-w','cc-h','cc-courier','cc-cod'].forEach(function(id) {
            var el = $(id);
            if (el) { el.addEventListener('input', recalc); el.addEventListener('change', recalc); }
        });

        recalc(); // run on load in case old() values are pre-filled
    })();
    </script>
    @endpush
</x-app-layout>
