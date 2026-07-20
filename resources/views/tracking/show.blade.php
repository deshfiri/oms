<x-app-layout>
    @section('title', 'Consignment · '.($consignment->consignment_id ?? $consignment->tracking_code))
    <div class="admin-page-header">
        <div>
            <h1>{{ $consignment->consignment_id ?? '—' }}</h1>
            <p class="sub">
                {{ strtoupper($consignment->courier_slug) }}
                @if($consignment->tracking_code)· tracking <x-tracking-cell :code="$consignment->tracking_code"/>@endif
                · order <a href="{{ route('orders.show', $consignment->order) }}">{{ optional($consignment->order)->order_number }}</a>
            </p>
        </div>
        @if($consignment->trackingUrl())<a href="{{ $consignment->trackingUrl() }}" target="_blank" rel="noopener" class="btn btn-outline">Track on courier ↗</a>@endif
        <a href="{{ route('tracking.index') }}" class="btn btn-outline">← Back</a>
    </div>
    <div class="admin-card">
        <div class="admin-card-head"><x-admin.section-head icon="clock" title="Events"/></div>
        @if($consignment->events->isEmpty())
            <x-admin.empty icon="clock" title="No events yet"/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>When</th><th>Status</th><th>Location</th><th>Remark</th></tr></thead>
                    <tbody>
                        @foreach($consignment->events as $e)
                            <tr>
                                <td>{{ $e->happened_at->format('d M Y H:i') }}</td>
                                <td><x-admin.pill :status="$e->status"/></td>
                                <td>{{ $e->location }}</td>
                                <td>{{ $e->remark }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
