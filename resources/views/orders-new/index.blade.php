<x-app-layout>
    @section('title', 'My orders')
    <div class="admin-page-header">
        <div><h1>Orders I placed</h1><p class="sub">{{ $orders->total() }} order(s). Created here, awaiting CS verification.</p></div>
        <a href="{{ route('orders-new.create') }}" class="btn btn-dark">+ Register order</a>
    </div>

    <form method="GET" class="admin-card" style="margin-bottom:14px">
        <div class="admin-card-body" style="display:flex;gap:10px">
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Order # · name · phone" style="width:280px">
            <button class="btn btn-dark">Apply</button>
        </div>
    </form>

    <div class="admin-card">
        <div class="admin-card-head"><x-admin.section-head icon="cart" title="My placed orders"/></div>
        @if($orders->isEmpty())
            <x-admin.empty icon="cart" title="No orders yet" :ctaUrl="route('orders-new.create')" ctaLabel="Place an order"/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>Order</th><th>Store</th><th>Customer</th><th>Placed</th><th>Status</th><th style="text-align:right">Total</th><th></th></tr></thead>
                    <tbody>
                    @foreach($orders as $o)
                        <tr>
                            <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a></td>
                            <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
                            <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div></td>
                            <td>{{ optional($o->placed_at)->diffForHumans() }}</td>
                            <td><x-admin.pill :status="$o->status" :label="$o->statusLabel()"/></td>
                            <td style="text-align:right;font-weight:600">৳{{ number_format($o->grand_total) }}</td>
                            <td style="text-align:right;white-space:nowrap">
                                <div style="display:inline-flex;gap:4px">
                                    <a href="{{ route('orders.show', $o) }}" class="btn btn-outline btn-sm">View</a>
                                    @if($o->status === \App\Services\Orders\OrderStateMachine::PENDING_VERIFICATION)
                                        <form method="POST" action="{{ route('orders-new.destroy', $o) }}" onsubmit="return confirm('Delete this order? It hasn\'t been confirmed yet.')">@csrf @method('DELETE')
                                            <button class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    @else
                                        <button class="btn btn-ghost btn-sm" disabled title="Confirmed — locked">Locked</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $orders->links() }}</div>
        @endif
    </div>
</x-app-layout>
