<x-app-layout>
    @section('title', 'Dashboard')

    <div class="admin-page-header">
        <div>
            <h1>Dashboard</h1>
            <p class="sub">Real-time snapshot across every connected store.</p>
        </div>
    </div>

    <x-admin.stat-strip :items="$cards" />

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:14px">
        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="cart" title="Recent orders" description="Most recent orders from any merchant."/></div>
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>Order</th><th>Store</th><th>Customer</th><th>Status</th><th style="text-align:right">Total</th></tr></thead>
                    <tbody>
                    @forelse($recentOrders as $o)
                        <tr>
                            <td><a href="{{ route('orders.show', $o) }}" style="font-weight:700;color:var(--a-accent);text-decoration:none">{{ $o->order_number }}</a></td>
                            <td style="font-size:12px"><strong>{{ optional($o->store)->dfid ?? '—' }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name ?? '' }}</div></td>
                            <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div></td>
                            <td><x-admin.pill :status="$o->status" :label="$o->statusLabel()"/></td>
                            <td style="text-align:right;font-weight:600">৳{{ number_format($o->grand_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-admin.empty icon="inbox" title="No orders yet" description="Register a store and run sync to pull orders from DFCOMMERCE." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="inventory" title="Low stock" description="At or below threshold on any store."/></div>
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>SKU</th><th>Store</th><th>Name</th><th style="text-align:right">Qty</th></tr></thead>
                    <tbody>
                    @forelse($lowStock as $p)
                        <tr>
                            <td style="font-family:ui-monospace,monospace;font-size:12px">{{ $p->sku }}</td>
                            <td style="font-size:12px"><strong>{{ optional($p->store)->dfid ?? '—' }}</strong></td>
                            <td>{{ $p->name }}</td>
                            <td style="text-align:right;font-weight:700;color:var(--a-danger,#dc2626)">{{ $p->stock_quantity }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-admin.empty icon="check" title="Nothing low" description="All managed SKUs are above their threshold." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
