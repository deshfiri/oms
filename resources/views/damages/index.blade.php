<x-app-layout>
    @section('title', 'Damages')
    <div class="admin-page-header">
        <div><h1>Damage register</h1><p class="sub">{{ $items->total() }} record(s) across all stores.</p></div>
        <a href="{{ route('damages.create') }}" class="btn btn-dark">+ Record damage</a>
    </div>

    <div class="admin-card">
        <div class="admin-card-head"><x-admin.section-head icon="warning" title="Recorded damages"/></div>
        @if($items->isEmpty())
            <x-admin.empty icon="warning" title="No damages yet" :ctaUrl="route('damages.create')" ctaLabel="Record one"/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>SKU</th><th>Store</th><th>Qty</th><th>Reason</th><th>By</th><th>When</th><th>Synced</th></tr></thead>
                    <tbody>
                    @foreach($items as $d)
                        <tr>
                            <td style="font-family:ui-monospace,monospace;font-size:12px">{{ $d->sku }}</td>
                            <td style="font-size:12px"><strong>{{ optional($d->store)->dfid ?? '—' }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($d->store)->business_name ?? '' }}</div></td>
                            <td>{{ $d->qty }}</td>
                            <td>{{ $d->reason }}</td>
                            <td>{{ optional($d->recorder)->name }}</td>
                            <td>{{ optional($d->recorded_at)->diffForHumans() }}</td>
                            <td>{{ $d->posted_to_storefront_at ? '✓' : '×' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $items->links() }}</div>
        @endif
    </div>
</x-app-layout>
