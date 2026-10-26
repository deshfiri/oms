<x-app-layout>
    @section('title', 'Register order')
    <div class="admin-page-header">
        <div>
            <h1>Register order</h1>
            <p class="sub">Goes straight to Pending Verification. CS will call to confirm before it enters the
                warehouse.</p>
        </div>
        <a href="{{ route('orders-new.index') }}" class="btn btn-outline">← Back</a>
    </div>

    <form method="POST" action="{{ route('orders-new.store') }}" x-data="orderForm()"
        @submit="syncItemsBeforeSubmit($event)">
        @csrf

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px">
            <div style="display:flex;flex-direction:column;gap:14px">
                {{-- Store + payment --}}
                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="settings" title="Order setup" /></div>
                    <div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <label style="grid-column:1/-1"><span style="font-size:12px;font-weight:600">Store *</span>
                            <select class="input" name="store_id" x-model="storeId" required
                                @change="items = []; clearOriginal(); refreshProductSearch()">
                                <option value="">— select business —</option>
                                @foreach($stores as $s)
                                    <option value="{{ $s->id }}">
                                        {{ ($s->dfid ? $s->dfid . ' · ' : '') . ($s->business_name ?? $s->name) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label x-show="!exchange"><span style="font-size:12px;font-weight:600">Payment *</span>
                            <select class="input" name="payment_method" required :disabled="exchange">
                                <option value="cod">Cash on delivery</option>
                                <option value="bkash">bKash</option>
                                <option value="nagad">Nagad</option>
                                <option value="rocket">Rocket</option>
                                <option value="upay">Upay</option>
                                <option value="bank_transfer">Bank transfer</option>
                                <option value="sslcommerz">SSLCommerz</option>
                                <option value="shurjopay">ShurjoPay</option>
                                <option value="amarpay">aamarPay</option>
                                <option value="eps">EPS</option>
                            </select>
                        </label>
                        <label class="xo-toggle" :class="exchange && 'is-on'">
                            <input type="checkbox" x-model="exchange" @change="if (exchange && storeId) lookupOriginal()">
                            <span><strong>Exchange order</strong>
                                <small>Replacement for a delivered order</small></span>
                        </label>
                        <input type="hidden" name="is_exchange" :value="exchange ? 1 : 0">
                    </div>
                </div>

                {{-- Exchange mode: which delivered order is being exchanged --}}
                <div class="admin-card" x-show="exchange" x-cloak>
                    <div class="admin-card-head"><x-admin.section-head icon="swap" title="Exchange details"
                            description="The replacement ships to the original order's customer — free re-delivery, straight to Processing." /></div>
                    <div class="admin-card-body" style="display:flex;flex-direction:column;gap:10px">
                        <input type="hidden" name="original_order_id" :value="original ? original.id : ''" :disabled="!exchange">

                        <div x-show="!original" style="display:flex;flex-direction:column;gap:6px;position:relative">
                            <span style="font-size:12px;font-weight:600">Original order * <span style="font-weight:400;color:var(--a-text-3)">(delivered orders of this store)</span></span>
                            <input class="input" type="text" x-model="originalQuery"
                                @input.debounce.250ms="lookupOriginal()" @focus="lookupOriginal()"
                                @click.away="originalResults = []"
                                :disabled="!storeId"
                                :placeholder="storeId ? 'Search order # · customer name · phone' : 'Pick a store first'">
                            <div x-show="originalResults.length" x-cloak
                                style="border:1px solid var(--a-border);border-radius:var(--a-r);background:var(--a-surface);max-height:260px;overflow-y:auto">
                                <template x-for="o in originalResults" :key="o.id">
                                    <div @click="pickOriginal(o)"
                                        style="padding:8px 12px;border-bottom:1px solid var(--a-border);cursor:pointer;font-size:13px"
                                        onmouseover="this.style.background='var(--a-surface-2)'" onmouseout="this.style.background=''">
                                        <div style="display:flex;justify-content:space-between;gap:8px">
                                            <strong x-text="o.order_number"></strong>
                                            <span style="color:var(--a-text-3);font-size:11px" x-text="o.status + ' · ' + (o.placed_at || '')"></span>
                                        </div>
                                        <div style="font-size:12px;color:var(--a-text-2)" x-text="o.name + ' · ' + o.phone"></div>
                                        <div style="font-size:11px;color:var(--a-text-3)" x-text="o.items.map(i => i.qty + '× ' + i.name).join(', ')"></div>
                                    </div>
                                </template>
                            </div>
                            <div x-show="storeId && originalSearched && !originalResults.length" x-cloak
                                style="font-size:12px;color:var(--a-text-3)">No delivered order matches — only delivered orders can be exchanged.</div>
                        </div>

                        <template x-if="original">
                            <div class="xo-original">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                                    <strong x-text="'Exchanging ' + original.order_number"></strong>
                                    <button type="button" class="btn btn-outline btn-sm" @click="clearOriginal()">Change</button>
                                </div>
                                <div style="font-size:12px;color:var(--a-text-2)" x-text="original.name + ' · ' + original.phone"></div>
                                <div style="font-size:12px;color:var(--a-text-2)" x-text="original.address"></div>
                                <div style="font-size:12px;margin-top:6px"><span style="font-weight:600">Customer returns:</span>
                                    <span x-text="original.items.map(i => i.qty + '× ' + i.name + (i.variant ? ' (' + i.variant + ')' : '')).join(', ')"></span></div>
                            </div>
                        </template>

                        <label><span style="font-size:12px;font-weight:600">Reason *</span>
                            <select class="input" name="exchange_reason" :required="exchange" :disabled="!exchange">
                                <option value="">— reason —</option>
                                @foreach($reasons as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div style="font-size:11px;color:var(--a-text-3)">If this store ships with Steadfast: the Steadfast API has no exchange option, so this order is not auto-booked.
                            Create it in the Steadfast panel with <strong>Exchange parcel</strong> ticked, then enter its consignment ID from Processing.</div>
                    </div>
                </div>

                {{-- Customer & delivery — mirrors the storefront checkout fields (not used for exchanges) --}}
                <fieldset class="admin-card" x-show="!exchange" :disabled="exchange" style="margin:0;padding:0;min-width:0">
                    <div class="admin-card-head"><x-admin.section-head icon="user" title="Customer details"
                            description="Same fields as the website checkout." /></div>
                    <div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <label><span style="font-size:12px;font-weight:600">Full name *</span><input class="input"
                                name="customer_name" required autocomplete="off"></label>
                        <label><span style="font-size:12px;font-weight:600">Mobile number *</span>
                            <span style="display:flex;gap:6px">
                                <input class="input" name="customer_phone" x-model="customerPhone" required
                                    inputmode="numeric" pattern="01[3-9][0-9]{8}" maxlength="15"
                                    placeholder="01XXXXXXXXX" style="flex:1"
                                    title="11-digit Bangladeshi mobile, e.g. 01712345678"
                                    @change="const n = normalizedPhone(); if (n) customerPhone = n">
                                <button type="button" class="btn btn-outline"
                                    style="white-space:nowrap;height:auto"
                                    :disabled="fraudChecking || !storeId || !phoneValid()"
                                    @click="checkFraud()"
                                    x-text="fraudChecking ? 'Checking...' : 'Fraud Check'">Fraud Check</button>
                            </span>
                        </label>
                        <label style="grid-column:1/-1"><span style="font-size:12px;font-weight:600">Full address
                                *</span><input class="input" name="shipping_address_line" required
                                placeholder="House / road / block"></label>
                        <label><span style="font-size:12px;font-weight:600">District *</span>
                            <select class="input" name="shipping_district" x-model="district"
                                @change="onDistrictChange()" required>
                                <option value="">— select district —</option>
                                <template x-for="d in districtList" :key="d.district">
                                    <option :value="d.district" x-text="d.district"></option>
                                </template>
                            </select>
                        </label>
                        <label><span style="font-size:12px;font-weight:600">Area</span>
                            <select class="input" name="shipping_area" x-model="area" :disabled="!areas.length"
                                @change="fetchShipping()">
                                <option value="">— select area —</option>
                                <template x-for="a in areas" :key="a">
                                    <option :value="a" x-text="a"></option>
                                </template>
                            </select>
                        </label>
                        {{-- Hidden, derived from district --}}
                        <input type="hidden" name="shipping_city" :value="district">
                        <input type="hidden" name="shipping_zone" :value="zone">
                    </div>
                </fieldset>

                {{-- Items --}}
                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="cart" title="Items"
                            description="Search the store catalogue. Prices come from the catalogue." /></div>
                    <div class="admin-card-body" style="display:flex;flex-direction:column;gap:10px">
                        <div x-show="exchange" x-cloak style="font-size:12px;font-weight:600;color:var(--a-accent)">Replacement products — what the customer receives in exchange.</div>
                        <div style="display:flex;gap:8px;position:relative">
                            <input class="input" type="text" x-model="search"
                                @input.debounce.250ms="refreshProductSearch" @focus="if(storeId) refreshProductSearch()"
                                @click.away="searchResults = []"
                                placeholder="Search products by SKU or name (after picking a store)"
                                :disabled="!storeId" style="flex:1">
                        </div>

                        {{-- Search results --}}
                        <div x-show="searchResults.length" x-cloak
                            style="border:1px solid var(--a-border);border-radius:var(--a-r);background:#fff;max-height:240px;overflow-y:auto">
                            <template x-for="p in searchResults" :key="p.sku">
                                <div @click="addItem(p)"
                                    style="padding:8px 12px;border-bottom:1px solid var(--a-border);cursor:pointer;display:flex;justify-content:space-between;font-size:13px"
                                    onmouseover="this.style.background='var(--a-surface-2)'"
                                    onmouseout="this.style.background=''">
                                    <span><strong x-text="p.name"></strong> <span
                                            style="font-family:ui-monospace,monospace;color:var(--a-text-3);font-size:11px;margin-left:6px"
                                            x-text="p.sku"></span></span>
                                    <span><strong x-text="'৳' + (p.sale_price || p.price)"></strong> <span
                                            style="color:var(--a-text-3);font-size:11px"
                                            x-text="'stock ' + p.stock_quantity"></span>
                                    </span>
                                </div>
                            </template>
                        </div>

                        {{-- Picked items --}}
                        <div class="atable-wrap">
                            <table class="atable">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Item</th>
                                        <th style="width:90px;text-align:right">Qty</th>
                                        <th style="text-align:right">Unit</th>
                                        <th style="text-align:right">Line total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(it, idx) in items" :key="it._id">
                                        <tr>
                                            <td style="font-family:ui-monospace,monospace;font-size:12px"
                                                x-text="it.sku"></td>
                                            <td x-text="it.name"></td>
                                            <td><input class="input" type="number" min="1" x-model.number="it.qty"
                                                    style="width:70px;height:34px;padding:0 8px;text-align:right"></td>
                                            <td style="text-align:right;color:var(--a-text-2)"
                                                x-text="'৳' + it.unit_price"></td>
                                            <td style="text-align:right;font-weight:600"
                                                x-text="'৳' + (it.qty * it.unit_price).toFixed(2)"></td>
                                            <td style="text-align:right"><button type="button"
                                                    class="btn btn-danger btn-sm"
                                                    @click="removeItem(idx)">Remove</button></td>
                                        </tr>
                                    </template>
                                    <tr x-show="!items.length">
                                        <td colspan="6" style="padding:24px;text-align:center;color:var(--a-text-3)">No
                                            items yet — search above to add.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Hidden field that gets populated on submit --}}
                        <div id="hidden-items"></div>
                    </div>
                </div>
            </div>

            {{-- Sidebar: totals + notes + submit --}}
            <div style="display:flex;flex-direction:column;gap:14px">
                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="tag" title="Adjustments" /></div>
                    <div class="admin-card-body" style="display:flex;flex-direction:column;gap:8px">
                        <label><span style="font-size:12px;font-weight:600">Discount (৳)</span><input class="input"
                                name="discount" type="number" step="0.01" min="0" value="0"
                                x-model.number="discount"></label>
                        <label x-show="!exchange"><span style="font-size:12px;font-weight:600">Coupon code</span><input class="input"
                                name="coupon_code" :disabled="exchange"></label>
                        <div
                            style="font-size:12px;color:var(--a-text-3);display:flex;justify-content:space-between;padding-top:4px">
                            <span>Delivery charge <span x-show="shippingLoading && !exchange">· fetching…</span></span>
                            <strong style="color:var(--a-text-2)"
                                x-text="exchange ? '৳0.00' : (zone ? ('৳' + (shipping||0).toFixed(2)) : '—')"></strong>
                        </div>
                        <div x-show="!exchange" style="font-size:11px;color:var(--a-text-3)">Pulled from the store website by zone — not
                            editable.</div>
                        <div x-show="exchange" x-cloak style="font-size:11px;color:var(--a-text-3)">Exchange: free re-delivery. Use Discount to
                            charge only the price difference.</div>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="cart" title="Totals" /></div>
                    <div class="admin-card-body" style="font-size:13px">
                        <div style="display:flex;justify-content:space-between"><span>Subtotal</span><strong
                                x-text="'৳' + subtotal().toFixed(2)"></strong></div>
                        <div style="display:flex;justify-content:space-between;color:var(--a-text-2)">
                            <span>Discount</span><span x-text="'−৳' + (discount||0).toFixed(2)"></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;color:var(--a-text-2)"><span x-text="exchange ? 'Delivery (exchange)' : 'Delivery (website)'">Delivery
                                (website)</span><span x-text="'৳' + deliveryCharge().toFixed(2)"></span></div>
                        <hr style="border-color:var(--a-border)">
                        <div style="display:flex;justify-content:space-between;font-size:15px"><strong>Grand
                                total</strong><strong x-text="'৳' + grandTotal().toFixed(2)"></strong></div>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="clock" title="Notes" /></div>
                    <div class="admin-card-body" style="display:flex;flex-direction:column;gap:10px">
                        <label><span style="font-size:12px;font-weight:600">Internal note</span>
                            <textarea class="input" name="notes" rows="3" placeholder="Internal note for CS"></textarea></label>
                        <label><span style="font-size:12px;font-weight:600">Courier note</span>
                            <textarea class="input" name="courier_note" rows="2" maxlength="{{ \App\Models\OrderMirror::COURIER_NOTE_MAX }}"
                                x-model="courierNote"
                                placeholder="For the courier / rider, e.g. URGENT: please deliver as soon as possible."></textarea>
                            <span style="display:flex;justify-content:space-between;gap:8px;font-size:11px;color:var(--a-text-3);margin-top:2px">
                                <span>Sent with the courier booking. Not for internal remarks.</span>
                                <span x-text="(courierNote || '').length + '/{{ \App\Models\OrderMirror::COURIER_NOTE_MAX }}'"></span>
                            </span></label>
                    </div>
                </div>

                <button class="btn btn-dark btn-lg btn-block"
                    x-text="exchange ? 'Create exchange order — send to Processing' : 'Place order — send to verification'">Place order — send to verification</button>
            </div>
        </div>

        {{-- ── Courier Fraud Check modal ─────────────────────────────────── --}}
        <template x-teleport="body">
            <div x-show="fraudModal" x-cloak @keydown.escape.window="fraudModal = false"
                style="position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px">
                <div @click="fraudModal = false" style="position:absolute;inset:0;background:rgba(0,0,0,.45)"></div>
                <div class="admin-card" style="position:relative;width:min(840px,92vw);max-height:85vh;overflow-y:auto;margin:auto">
                <div class="admin-card-head" style="display:flex;justify-content:space-between;align-items:center">
                    <x-admin.section-head icon="user" title="Courier Fraud Check" />
                    <button type="button" class="btn btn-outline btn-sm" @click="fraudModal = false">✕</button>
                </div>
                <div class="admin-card-body" style="font-size:13px;display:flex;flex-direction:column;gap:10px">

                    {{-- Error state --}}
                    <template x-if="fraudError">
                        <div style="padding:10px 12px;border:1px solid #f3c2c2;background:#fdf2f2;color:#8a2424;border-radius:var(--a-r)"
                            x-text="fraudError"></div>
                    </template>

                    {{-- Result state --}}
                    <template x-if="fraudResult">
                        <div style="display:flex;flex-direction:column;gap:10px">
                            <div style="display:grid;grid-template-columns:auto 1fr;gap:4px 12px;color:var(--a-text-2)">
                                <span>Store</span><strong style="color:var(--a-text-1)" x-text="fraudResult.store"></strong>
                                <span>Courier</span><strong style="color:var(--a-text-1)" x-text="fraudResult.courier"></strong>
                                <span>Phone</span><strong style="color:var(--a-text-1);font-family:ui-monospace,monospace" x-text="fraudResult.phone"></strong>
                            </div>

                            <div :style="'padding:10px 12px;border-radius:var(--a-r);font-weight:600;' + riskStyle(fraudResult.risk?.level)">
                                <span x-text="fraudResult.risk?.label || 'Unable to Determine'"></span>
                                <div style="font-weight:400;font-size:12px;margin-top:2px"
                                    x-text="fraudResult.risk?.message || ''"></div>
                            </div>

                            <div class="atable-wrap">
                                <table class="atable">
                                    <tbody>
                                        <tr><td>Total parcels</td><td style="text-align:right"><strong x-text="fraudResult.summary.total"></strong></td></tr>
                                        <tr><td>Delivered</td><td style="text-align:right"><strong x-text="fraudResult.summary.delivered"></strong></td></tr>
                                        <tr><td>Returned</td><td style="text-align:right"><strong x-text="fraudResult.summary.returned"></strong></td></tr>
                                        <tr><td>Cancelled</td><td style="text-align:right"><strong x-text="fraudResult.summary.cancelled"></strong></td></tr>
                                        <tr><td>Pending</td><td style="text-align:right"><strong x-text="fraudResult.summary.pending"></strong></td></tr>
                                        <tr><td>Success rate</td><td style="text-align:right"><strong x-text="fraudResult.summary.success_rate != null ? fraudResult.summary.success_rate + '%' : '—'"></strong></td></tr>
                                        <tr><td>Return / cancel rate</td><td style="text-align:right"><strong x-text="fraudResult.summary.return_rate != null ? fraudResult.summary.return_rate + '%' : '—'"></strong></td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <div x-show="fraudResult.raw_reference" style="font-size:11px;color:var(--a-text-3)"
                                x-text="fraudResult.raw_reference"></div>
                        </div>
                    </template>

                    <button type="button" class="btn btn-dark btn-block" @click="fraudModal = false">Close</button>
                </div>
                </div>
            </div>
        </template>
    </form>

    @push('scripts')
        <script defer src="/js/alpine.min.js"></script>
        <style>
            [x-cloak] {
                display: none !important
            }
            /* Exchange order toggle (same idea as Steadfast's "Exchange parcel" box) */
            .xo-toggle { display:flex; align-items:center; gap:10px; align-self:end; min-height:42px; padding:8px 12px;
                border:1px solid var(--a-border); border-radius:var(--a-r); cursor:pointer; }
            .xo-toggle.is-on { border-color:var(--a-accent-border); background:var(--a-accent-soft); }
            .xo-toggle input { width:18px; height:18px; accent-color:var(--a-accent); cursor:pointer; flex:none; }
            .xo-toggle span { display:flex; flex-direction:column; line-height:1.2; font-size:13px; }
            .xo-toggle small { font-size:11px; color:var(--a-text-3); }
            .xo-original { border:1px solid var(--a-accent-border); background:var(--a-accent-soft); border-radius:var(--a-r); padding:10px 12px; }
        </style>
        <script>
            function orderForm() {
                return {
                    storeId: '',
                    search: '',
                    searchResults: [],
                    items: [],
                    discount: 0,
                    shipping: 0,
                    shippingLoading: false,
                    customerPhone: '',
                    courierNote: '',
                    fraudChecking: false,
                    fraudModal: false,
                    fraudResult: null,
                    fraudError: null,
                    districtList: @json($districts),
                    district: '',
                    area: '',
                    areas: [],
                    zone: '',
                    // Exchange mode
                    exchange: false,
                    original: null,
                    originalQuery: '',
                    originalResults: [],
                    originalSearched: false,

                    init() {
                        // Re-quote the delivery charge whenever items or store change.
                        this.$watch('items', () => this.fetchShipping(), { deep: true });
                        this.$watch('storeId', () => this.fetchShipping());
                    },
                    onDistrictChange() {
                        const d = this.districtList.find(x => x.district === this.district);
                        this.areas = d ? d.areas : [];
                        this.area = '';
                        this.zone = this.district ? (this.district === 'Dhaka' ? 'inside_dhaka' : 'outside_dhaka') : '';
                        this.fetchShipping();
                    },
                    async fetchShipping() {
                        if (!this.storeId || !this.zone) { this.shipping = 0; return; }
                        this.shippingLoading = true;
                        try {
                            const u = new URL('{{ route("orders-new.shipping-quote") }}', location.origin);
                            u.searchParams.set('store_id', this.storeId);
                            u.searchParams.set('zone', this.zone);
                            u.searchParams.set('subtotal', this.subtotal());
                            const r = await fetch(u.toString(), { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
                            const j = await r.json();
                            this.shipping = (j.ok && j.shipping_total != null) ? parseFloat(j.shipping_total) : 0;
                        } catch (e) { this.shipping = 0; }
                        this.shippingLoading = false;
                    },

                    // Accept 01…, +8801…, 8801… and reduce to canonical 01XXXXXXXXX.
                    normalizedPhone() {
                        let d = (this.customerPhone || '').replace(/\D+/g, '');
                        if (d.startsWith('880')) d = d.slice(2);
                        return /^01[3-9]\d{8}$/.test(d) ? d : null;
                    },
                    phoneValid() { return this.normalizedPhone() !== null; },
                    riskStyle(level) {
                        switch (level) {
                            case 'low':    return 'border:1px solid #bfe3c5;background:#effaf1;color:#1d6b2f';
                            case 'medium': return 'border:1px solid #f2ddb0;background:#fdf8ec;color:#8a6410';
                            case 'high':   return 'border:1px solid #f3c2c2;background:#fdf2f2;color:#8a2424';
                            default:       return 'border:1px solid var(--a-border);background:var(--a-surface-2);color:var(--a-text-2)';
                        }
                    },
                    async checkFraud() {
                        if (this.fraudChecking || !this.storeId || !this.phoneValid()) return;
                        this.fraudChecking = true;
                        this.fraudResult = null;
                        this.fraudError = null;
                        try {
                            const r = await fetch('{{ route("orders-new.fraud-check") }}', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                },
                                body: JSON.stringify({ store_id: this.storeId, phone: this.normalizedPhone() }),
                            });
                            if (r.status === 429) {
                                this.fraudError = 'Too many checks — please wait a minute and try again.';
                            } else {
                                const j = await r.json();
                                if (j.success) this.fraudResult = j;
                                else this.fraudError = j.message || 'The courier service is currently unavailable. Please try again.';
                            }
                        } catch (e) {
                            this.fraudError = 'Network error — please try again.';
                        }
                        this.fraudChecking = false;
                        this.fraudModal = true;
                    },

                    async refreshProductSearch() {
                        if (!this.storeId) { this.searchResults = []; return; }
                        const u = new URL('{{ route("orders-new.product-search") }}', location.origin);
                        u.searchParams.set('store_id', this.storeId);
                        if (this.search) u.searchParams.set('q', this.search);
                        const r = await fetch(u.toString(), { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
                        this.searchResults = await r.json();
                    },
                    addItem(p) {
                        const existing = this.items.find(x => x.sku === p.sku);
                        if (existing) {
                            this.items = this.items.map(x => x.sku === p.sku ? { ...x, qty: x.qty + 1 } : x);
                        } else {
                            this.items = [...this.items, {
                                _id: Date.now() + Math.random(),
                                sku: p.sku, name: p.name,
                                unit_price: parseFloat(p.sale_price ?? p.price),
                                qty: 1,
                            }];
                        }
                        this.search = '';
                        this.searchResults = [];
                    },
                    removeItem(idx) {
                        this.items = this.items.filter((_, i) => i !== idx);
                    },
                    async lookupOriginal() {
                        if (!this.storeId || !this.exchange) { this.originalResults = []; return; }
                        const u = new URL('{{ route("orders-new.exchange-lookup") }}', location.origin);
                        u.searchParams.set('store_id', this.storeId);
                        if (this.originalQuery) u.searchParams.set('q', this.originalQuery);
                        try {
                            const r = await fetch(u.toString(), { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
                            this.originalResults = r.ok ? await r.json() : [];
                        } catch (e) { this.originalResults = []; }
                        this.originalSearched = true;
                    },
                    pickOriginal(o) {
                        this.original = o;
                        this.originalResults = [];
                        this.originalQuery = '';
                    },
                    clearOriginal() {
                        this.original = null;
                        this.originalResults = [];
                        this.originalSearched = false;
                    },

                    subtotal() { return this.items.reduce((a, it) => a + (it.qty * it.unit_price), 0); },
                    deliveryCharge() { return this.exchange ? 0 : (this.shipping || 0); },
                    grandTotal() {
                        return Math.max(0, this.subtotal() - (this.discount || 0) + this.deliveryCharge());
                    },
                    syncItemsBeforeSubmit(e) {
                        if (this.exchange && !this.original) { e.preventDefault(); alert('Pick the delivered order this exchange replaces.'); return; }
                        if (!this.items.length) { e.preventDefault(); alert(this.exchange ? 'Add at least one replacement product.' : 'Add at least one item.'); return; }
                        const slot = document.getElementById('hidden-items');
                        slot.innerHTML = '';
                        this.items.forEach((it, i) => {
                            const h1 = document.createElement('input'); h1.type = 'hidden'; h1.name = `items[${i}][sku]`; h1.value = it.sku; slot.appendChild(h1);
                            const h2 = document.createElement('input'); h2.type = 'hidden'; h2.name = `items[${i}][qty]`; h2.value = it.qty; slot.appendChild(h2);
                        });
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>