<x-app-layout>
    @section('title', 'Register order')
    <div class="admin-page-header">
        <div><h1>Register order</h1><p class="sub">Goes straight to Pending Verification. CS will call to confirm before it enters the warehouse.</p></div>
        <a href="{{ route('orders-new.index') }}" class="btn btn-outline">← Back</a>
    </div>

    <form method="POST" action="{{ route('orders-new.store') }}" x-data="orderForm()" @submit="syncItemsBeforeSubmit($event)">
        @csrf

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px">
            <div style="display:flex;flex-direction:column;gap:14px">

                {{-- Store + payment --}}
                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="settings" title="Order setup"/></div>
                    <div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <label style="grid-column:1/-1"><span style="font-size:12px;font-weight:600">Store *</span>
                            <select class="input" name="store_id" x-model="storeId" required @change="items = []; refreshProductSearch()">
                                <option value="">— select business —</option>
                                @foreach($stores as $s)
                                    <option value="{{ $s->id }}">{{ ($s->dfid?$s->dfid.' · ':'').($s->business_name ?? $s->name) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label><span style="font-size:12px;font-weight:600">Payment *</span>
                            <select class="input" name="payment_method" required>
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
                    </div>
                </div>

                {{-- Customer & delivery — mirrors the storefront checkout fields --}}
                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="user" title="Customer details" description="Same fields as the website checkout."/></div>
                    <div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <label><span style="font-size:12px;font-weight:600">Full name *</span><input class="input" name="customer_name" required autocomplete="off"></label>
                        <label><span style="font-size:12px;font-weight:600">Mobile number *</span><input class="input" name="customer_phone" required inputmode="numeric" pattern="01[3-9][0-9]{8}" maxlength="11" placeholder="01XXXXXXXXX" title="11-digit Bangladeshi mobile, e.g. 01712345678"></label>
                        <label style="grid-column:1/-1"><span style="font-size:12px;font-weight:600">Full address *</span><input class="input" name="shipping_address_line" required placeholder="House / road / block"></label>
                        <label><span style="font-size:12px;font-weight:600">District *</span>
                            <select class="input" name="shipping_district" x-model="district" @change="onDistrictChange()" required>
                                <option value="">— select district —</option>
                                <template x-for="d in districtList" :key="d.district">
                                    <option :value="d.district" x-text="d.district"></option>
                                </template>
                            </select>
                        </label>
                        <label><span style="font-size:12px;font-weight:600">Area</span>
                            <select class="input" name="shipping_area" x-model="area" :disabled="!areas.length" @change="fetchShipping()">
                                <option value="">— select area —</option>
                                <template x-for="a in areas" :key="a"><option :value="a" x-text="a"></option></template>
                            </select>
                        </label>
                        {{-- Hidden, derived from district --}}
                        <input type="hidden" name="shipping_city" :value="district">
                        <input type="hidden" name="shipping_zone" :value="zone">
                    </div>
                </div>

                {{-- Items --}}
                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="cart" title="Items" description="Search the store catalogue. Prices come from the catalogue."/></div>
                    <div class="admin-card-body" style="display:flex;flex-direction:column;gap:10px">
                        <div style="display:flex;gap:8px;position:relative">
                            <input class="input" type="text" x-model="search" @input.debounce.250ms="refreshProductSearch" placeholder="Search products by SKU or name (after picking a store)" :disabled="!storeId" style="flex:1">
                        </div>

                        {{-- Search results --}}
                        <div x-show="searchResults.length" x-cloak style="border:1px solid var(--a-border);border-radius:var(--a-r);background:#fff;max-height:240px;overflow-y:auto">
                            <template x-for="p in searchResults" :key="p.sku">
                                <div @click="addItem(p)" style="padding:8px 12px;border-bottom:1px solid var(--a-border);cursor:pointer;display:flex;justify-content:space-between;font-size:13px" onmouseover="this.style.background='var(--a-surface-2)'" onmouseout="this.style.background=''">
                                    <span><strong x-text="p.name"></strong> <span style="font-family:ui-monospace,monospace;color:var(--a-text-3);font-size:11px;margin-left:6px" x-text="p.sku"></span></span>
                                    <span><strong x-text="'৳' + (p.sale_price || p.price)"></strong> <span style="color:var(--a-text-3);font-size:11px" x-text="'stock ' + p.stock_quantity"></span></span>
                                </div>
                            </template>
                        </div>

                        {{-- Picked items --}}
                        <div class="atable-wrap">
                            <table class="atable">
                                <thead><tr><th>SKU</th><th>Item</th><th style="width:90px;text-align:right">Qty</th><th style="text-align:right">Unit</th><th style="text-align:right">Line total</th><th></th></tr></thead>
                                <tbody>
                                    <template x-for="(it, idx) in items" :key="it.sku">
                                        <tr>
                                            <td style="font-family:ui-monospace,monospace;font-size:12px" x-text="it.sku"></td>
                                            <td x-text="it.name"></td>
                                            <td><input class="input" type="number" min="1" x-model.number="it.qty" style="width:70px;height:34px;padding:0 8px;text-align:right"></td>
                                            <td style="text-align:right;color:var(--a-text-2)" x-text="'৳' + it.unit_price"></td>
                                            <td style="text-align:right;font-weight:600" x-text="'৳' + (it.qty * it.unit_price).toFixed(2)"></td>
                                            <td style="text-align:right"><button type="button" class="btn btn-danger btn-sm" @click="items.splice(idx, 1)">Remove</button></td>
                                        </tr>
                                    </template>
                                    <tr x-show="!items.length"><td colspan="6" style="padding:24px;text-align:center;color:var(--a-text-3)">No items yet — search above to add.</td></tr>
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
                    <div class="admin-card-head"><x-admin.section-head icon="tag" title="Adjustments"/></div>
                    <div class="admin-card-body" style="display:flex;flex-direction:column;gap:8px">
                        <label><span style="font-size:12px;font-weight:600">Discount (৳)</span><input class="input" name="discount" type="number" step="0.01" min="0" value="0" x-model.number="discount"></label>
                        <label><span style="font-size:12px;font-weight:600">Coupon code</span><input class="input" name="coupon_code"></label>
                        <div style="font-size:12px;color:var(--a-text-3);display:flex;justify-content:space-between;padding-top:4px">
                            <span>Delivery charge <span x-show="shippingLoading">· fetching…</span></span>
                            <strong style="color:var(--a-text-2)" x-text="zone ? ('৳' + (shipping||0).toFixed(2)) : '—'"></strong>
                        </div>
                        <div style="font-size:11px;color:var(--a-text-3)">Pulled from the store website by zone — not editable.</div>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="cart" title="Totals"/></div>
                    <div class="admin-card-body" style="font-size:13px">
                        <div style="display:flex;justify-content:space-between"><span>Subtotal</span><strong x-text="'৳' + subtotal().toFixed(2)"></strong></div>
                        <div style="display:flex;justify-content:space-between;color:var(--a-text-2)"><span>Discount</span><span x-text="'−৳' + (discount||0).toFixed(2)"></span></div>
                        <div style="display:flex;justify-content:space-between;color:var(--a-text-2)"><span>Delivery (website)</span><span x-text="'৳' + (shipping||0).toFixed(2)"></span></div>
                        <hr style="border-color:var(--a-border)">
                        <div style="display:flex;justify-content:space-between;font-size:15px"><strong>Grand total</strong><strong x-text="'৳' + grandTotal().toFixed(2)"></strong></div>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="clock" title="Notes"/></div>
                    <div class="admin-card-body"><textarea class="input" name="notes" rows="3" placeholder="Internal note for CS"></textarea></div>
                </div>

                <button class="btn btn-dark btn-lg btn-block">Place order — send to verification</button>
            </div>
        </div>
    </form>

    @push('scripts')
    <script defer src="/js/alpine.min.js"></script>
    <style>[x-cloak]{display:none !important}</style>
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
            districtList: @json($districts),
            district: '',
            area: '',
            areas: [],
            zone: '',

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
                    const r = await fetch(u.toString(), { headers: { 'Accept':'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
                    const j = await r.json();
                    this.shipping = (j.ok && j.shipping_total != null) ? parseFloat(j.shipping_total) : 0;
                } catch (e) { this.shipping = 0; }
                this.shippingLoading = false;
            },

            async refreshProductSearch() {
                if (!this.storeId) { this.searchResults = []; return; }
                const u = new URL('{{ route("orders-new.product-search") }}', location.origin);
                u.searchParams.set('store_id', this.storeId);
                if (this.search) u.searchParams.set('q', this.search);
                const r = await fetch(u.toString(), { headers: { 'Accept':'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
                this.searchResults = await r.json();
            },
            addItem(p) {
                const existing = this.items.find(x => x.sku === p.sku);
                if (existing) { existing.qty += 1; return; }
                this.items.push({
                    sku: p.sku, name: p.name,
                    unit_price: parseFloat(p.sale_price ?? p.price),
                    qty: 1,
                });
                this.search = '';
                this.searchResults = [];
            },
            subtotal() { return this.items.reduce((a, it) => a + (it.qty * it.unit_price), 0); },
            grandTotal() {
                return Math.max(0, this.subtotal() - (this.discount||0) + (this.shipping||0));
            },
            syncItemsBeforeSubmit(e) {
                if (!this.items.length) { e.preventDefault(); alert('Add at least one item.'); return; }
                const slot = document.getElementById('hidden-items');
                slot.innerHTML = '';
                this.items.forEach((it, i) => {
                    const h1 = document.createElement('input'); h1.type='hidden'; h1.name = `items[${i}][sku]`; h1.value = it.sku; slot.appendChild(h1);
                    const h2 = document.createElement('input'); h2.type='hidden'; h2.name = `items[${i}][qty]`; h2.value = it.qty; slot.appendChild(h2);
                });
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
