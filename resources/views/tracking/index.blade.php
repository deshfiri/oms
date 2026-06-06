<x-app-layout>
    @section('title', 'Tracking')
    <div class="admin-page-header"><div><h1>Tracking</h1><p class="sub">Live courier statuses from webhooks across every store. Tracked by Consignment ID.</p></div></div>

    @if($exceptions->count())
    <div class="admin-card" style="margin-bottom:14px;border:1px solid var(--a-danger,#dc2626)">
        <div class="admin-card-head"><x-admin.section-head icon="warning" title="Exceptions" :description="$exceptions->count().' need attention'"/></div>
        <div class="atable-wrap">
            <table class="atable">
                <thead><tr><th>Order</th><th>Store</th><th>Courier</th><th>Consignment ID</th><th>Tracking</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($exceptions as $c)
                    <tr>
                        <td>{{ optional($c->order)->order_number }}</td>
                        <td><strong>{{ optional($c->order->store ?? null)->dfid }}</strong></td>
                        <td>{{ strtoupper($c->courier_slug) }}</td>
                        <td style="font-family:ui-monospace,monospace;font-size:12.5px;font-weight:700">{{ $c->consignment_id ?? '—' }}</td>
                        <td style="font-family:ui-monospace,monospace;font-size:11px;color:var(--a-text-3)">{{ $c->tracking_code ?? '—' }}</td>
                        <td><x-admin.pill :status="$c->latest_status"/></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="admin-card">
        <div class="admin-card-head"><x-admin.section-head icon="pin" title="Active consignments"/></div>
        @if($inTransit->isEmpty())
            <x-admin.empty icon="truck" title="Nothing in transit"/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>Order</th><th>Store</th><th>Courier</th><th>Consignment ID</th><th>Tracking</th><th>Status</th><th>Booked</th><th></th></tr></thead>
                    <tbody>
                    @foreach($inTransit as $c)
                        <tr>
                            <td>{{ optional($c->order)->order_number }}</td>
                            <td><strong>{{ optional($c->order->store ?? null)->dfid }}</strong></td>
                            <td>{{ strtoupper($c->courier_slug) }}</td>
                            <td style="font-family:ui-monospace,monospace;font-size:12.5px;font-weight:700">{{ $c->consignment_id ?? '—' }}</td>
                            <td style="font-family:ui-monospace,monospace;font-size:11px;color:var(--a-text-3)">{{ $c->tracking_code ?? '—' }}</td>
                            <td><x-admin.pill :status="$c->latest_status"/></td>
                            <td style="font-size:12px">{{ optional($c->booked_at)->diffForHumans() }}</td>
                            <td style="text-align:right"><a href="{{ route('tracking.show', $c) }}" class="btn btn-outline btn-sm">Events</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $inTransit->links() }}</div>
        @endif
    </div>
    <x-live-refresh scope="tracking"/>
</x-app-layout>
