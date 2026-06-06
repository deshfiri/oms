<x-app-layout>
    @section('title', 'Inventory')
    <div class="admin-page-header"><div><h1>Inventory</h1><p class="sub">Products mirrored across {{ $stores->count() }} store(s).</p></div></div>

    <div class="admin-card">
        <div class="admin-card-head">
            <x-admin.section-head icon="inventory" title="Products"/>
            <form method="GET" style="display:flex;gap:8px">
                <select name="store_id" class="input" style="width:auto;padding:7px 10px;font-size:13px" onchange="this.form.submit()">
                    <option value="">All stores</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" @selected(request('store_id')==$s->id)>{{ ($s->dfid ? $s->dfid.' · ' : '').($s->business_name ?? $s->name) }}</option>
                    @endforeach
                </select>
                <input name="q" value="{{ request('q') }}" placeholder="SKU or name" class="input" style="padding:7px 10px;font-size:13px">
                <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px"><input type="checkbox" name="low" value="1" @checked(request('low'))> Low only</label>
                <button class="btn btn-dark">Filter</button>
            </form>
        </div>
        @if($products->isEmpty())
            <x-admin.empty icon="box" title="No products in mirror" description="Click Sync on the Stores page to pull products."/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>SKU</th><th>Store</th><th>Name</th><th>Bin</th><th style="text-align:right">Qty</th><th style="text-align:right">Threshold</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($products as $p)
                        <tr style="{{ $p->isLowStock() ? 'background:rgba(220,38,38,.05)' : '' }}">
                            <td style="font-family:ui-monospace,monospace;font-size:12px">{{ $p->sku }}</td>
                            <td style="font-size:12px"><strong>{{ optional($p->store)->dfid ?? '—' }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($p->store)->business_name ?? '' }}</div></td>
                            <td>{{ $p->name }}</td>
                            <td style="font-size:12px;color:var(--a-text-3)">{{ $p->bin_location ?? '—' }}</td>
                            <td style="text-align:right;font-weight:700">{{ $p->stock_quantity }}</td>
                            <td style="text-align:right">{{ $p->low_stock_threshold }}</td>
                            <td><x-admin.pill :status="$p->stock_status"/></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $products->links() }}</div>
        @endif
    </div>
</x-app-layout>
