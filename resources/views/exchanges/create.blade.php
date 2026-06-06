<x-app-layout>
    @section('title', 'Exchange · '.$order->order_number)
    <div class="admin-page-header">
        <div><h1>Issue exchange</h1><p class="sub">{{ $order->order_number }} · {{ $order->customer_name }} — pick the new product to send. The replacement ships from Processing; the old item is returned & inspected.</p></div>
        <a href="{{ route('orders.show', $order) }}" class="btn btn-outline">← Back to order</a>
    </div>

    <form method="POST" action="{{ route('exchanges.open', $order) }}" x-data="exchangeForm()" @submit="syncItems($event)">
        @csrf
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px">
            <div style="display:flex;flex-direction:column;gap:14px">

                {{-- Original (what the customer is sending back) --}}
                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="rotate" title="Returning (original)" description="The delivered item the customer sends back."/></div>
                    <div class="admin-card-body">
                        <ul style="margin:0;font-size:13px;color:var(--a-text-2)">
                            @foreach($order->items as $it)
                                <li>{{ $it->qty }}× {{ $it->name }} <span style="font-family:ui-monospace,monospace;color:var(--a-text-3);font-size:11px">{{ $it->sku }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                {{-- New product to send --}}
                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="cart" title="New product to send" description="Search this store's catalogue. Free re-delivery."/></div>
                    <div class="admin-card-body" style="display:flex;flex-direction:column;gap:10px">
                        <input class="input" type="text" x-model="search" @input.debounce.250ms="doSearch" placeholder="Search by SKU or name">
                        <div x-show="results.length" x-cloak style="border:1px solid var(--a-border);border-radius:var(--a-r);background:#fff;max-height:240px;overflow-y:auto">
                            <template x-for="p in results" :key="p.sku">
                                <div @click="add(p)" style="padding:8px 12px;border-bottom:1px solid var(--a-border);cursor:pointer;display:flex;justify-content:space-between;font-size:13px" onmouseover="this.style.background='var(--a-surface-2)'" onmouseout="this.style.background=''">
                                    <span><strong x-text="p.name"></strong> <span style="font-family:ui-monospace,monospace;color:var(--a-text-3);font-size:11px;margin-left:6px" x-text="p.sku"></span></span>
                                    <span><strong x-text="'৳' + (p.sale_price || p.price)"></strong> <span style="color:var(--a-text-3);font-size:11px" x-text="'stock ' + p.stock_quantity"></span></span>
                                </div>
                            </template>
                        </div>
                        <div class="atable-wrap">
                            <table class="atable">
                                <thead><tr><th>SKU</th><th>Item</th><th style="width:90px;text-align:right">Qty</th><th></th></tr></thead>
                                <tbody>
                                    <template x-for="(it, idx) in items" :key="it.sku">
                                        <tr>
                                            <td style="font-family:ui-monospace,monospace;font-size:12px" x-text="it.sku"></td>
                                            <td x-text="it.name"></td>
                                            <td><input class="input" type="number" min="1" x-model.number="it.qty" style="width:70px;height:34px;padding:0 8px;text-align:right"></td>
                                            <td style="text-align:right"><button type="button" class="btn btn-danger btn-sm" @click="items.splice(idx,1)">Remove</button></td>
                                        </tr>
                                    </template>
                                    <tr x-show="!items.length"><td colspan="4" style="padding:24px;text-align:center;color:var(--a-text-3)">No new item yet — search above.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="hidden-items"></div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div style="display:flex;flex-direction:column;gap:14px">
                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="swap" title="Exchange details"/></div>
                    <div class="admin-card-body" style="display:flex;flex-direction:column;gap:8px">
                        <label><span style="font-size:12px;font-weight:600">Reason *</span>
                            <select name="reason" class="input" required>
                                <option value="">— reason —</option>
                                <option value="wrong_item">Wrong item delivered</option>
                                <option value="size_issue">Size / fit issue</option>
                                <option value="defective">Defective / not working</option>
                                <option value="damaged">Damaged</option>
                                <option value="changed_mind">Changed selection</option>
                            </select>
                        </label>
                        <label><span style="font-size:12px;font-weight:600">Note</span><textarea name="notes" class="input" rows="3" placeholder="Optional"></textarea></label>
                    </div>
                </div>
                <button class="btn btn-dark btn-lg btn-block">Issue exchange → replacement to Processing</button>
            </div>
        </div>
    </form>

    @push('scripts')
    <script defer src="/js/alpine.min.js"></script>
    <style>[x-cloak]{display:none !important}</style>
    <script>
    function exchangeForm() {
        return {
            search: '', results: [], items: [],
            async doSearch() {
                const u = new URL('{{ route("exchanges.product-search", $order) }}', location.origin);
                if (this.search) u.searchParams.set('q', this.search);
                const r = await fetch(u.toString(), { headers: { 'Accept':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}' } });
                this.results = await r.json();
            },
            add(p) {
                const e = this.items.find(x => x.sku === p.sku);
                if (e) { e.qty += 1; } else { this.items.push({ sku:p.sku, name:p.name, qty:1 }); }
                this.search = ''; this.results = [];
            },
            syncItems(e) {
                if (!this.items.length) { e.preventDefault(); alert('Pick at least one new product to send.'); return; }
                const slot = document.getElementById('hidden-items'); slot.innerHTML = '';
                this.items.forEach((it, i) => {
                    const a = document.createElement('input'); a.type='hidden'; a.name=`items[${i}][sku]`; a.value=it.sku; slot.appendChild(a);
                    const b = document.createElement('input'); b.type='hidden'; b.name=`items[${i}][qty]`; b.value=it.qty; slot.appendChild(b);
                });
            },
        };
    }
    </script>
    @endpush
</x-app-layout>
