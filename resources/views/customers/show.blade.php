<x-app-layout>
    @section('title', $customer->name)
    <div class="admin-page-header"><div><h1>{{ $customer->name }}</h1><p class="sub">{{ $customer->phone }} · {{ $customer->email }}</p></div></div>

    <div class="admin-card">
        <div class="admin-card-head"><x-admin.section-head icon="cart" title="Order history"/></div>
        @if($orders->isEmpty())
            <x-admin.empty icon="cart" title="No orders"/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>Order</th><th>Placed</th><th>Status</th><th style="text-align:right">Total</th></tr></thead>
                    <tbody>
                    @foreach($orders as $o)
                        <tr>
                            <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:600;text-decoration:none">{{ $o->order_number }}</a></td>
                            <td>{{ optional($o->placed_at)->diffForHumans() }}</td>
                            <td><x-admin.pill :status="$o->status"/></td>
                            <td style="text-align:right;font-weight:600">৳{{ number_format($o->grand_total, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
