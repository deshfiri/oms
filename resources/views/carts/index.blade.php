<x-app-layout>
    @section('title', 'Carts')
    <div class="admin-page-header"><div><h1>Abandoned carts & incomplete orders</h1><p class="sub">Aggregated from every active store.</p></div></div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:14px">
        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="cart" :title="'Abandoned ('.count($abandoned).')'"/></div>
            @if(empty($abandoned))
                <x-admin.empty icon="cart" title="None"/>
            @else
                <div class="atable-wrap">
                    <table class="atable">
                        <thead><tr><th>Store</th><th>Customer</th><th>Items</th><th style="text-align:right">Value</th></tr></thead>
                        <tbody>
                        @foreach($abandoned as $c)
                            <tr>
                                <td style="font-size:12px">{{ $c['_store'] ?? '' }}</td>
                                <td>{{ $c['customer_name'] ?? '—' }}<div style="font-size:11px;color:var(--a-text-3)">{{ $c['customer_phone'] ?? '' }}</div></td>
                                <td>{{ $c['item_count'] ?? count($c['items'] ?? []) }}</td>
                                <td style="text-align:right;font-weight:600">৳{{ number_format($c['subtotal'] ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="clock" :title="'Incomplete ('.count($incomplete).')'"/></div>
            @if(empty($incomplete))
                <x-admin.empty icon="clock" title="None"/>
            @else
                <div class="atable-wrap">
                    <table class="atable">
                        <thead><tr><th>Store</th><th>Customer</th><th>Stage</th><th style="text-align:right">Cart value</th></tr></thead>
                        <tbody>
                        @foreach($incomplete as $c)
                            <tr>
                                <td style="font-size:12px">{{ $c['_store'] ?? '' }}</td>
                                <td>{{ $c['customer_name'] ?? '—' }}<div style="font-size:11px;color:var(--a-text-3)">{{ $c['customer_phone'] ?? '' }}</div></td>
                                <td>{{ $c['last_step'] ?? '—' }}</td>
                                <td style="text-align:right;font-weight:600">৳{{ number_format($c['cart_value'] ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
