<x-app-layout>
    @section('title', 'Stores')
    <div class="admin-page-header">
        <div><h1>Stores</h1><p class="sub">{{ $stores->count() }} merchant(s) connected.</p></div>
        <a href="{{ route('stores.create') }}" class="btn btn-dark">+ Add store</a>
    </div>

    <div class="admin-card">
        <div class="admin-card-head"><x-admin.section-head icon="settings" title="All merchants"/></div>
        @if($stores->isEmpty())
            <x-admin.empty icon="settings" title="No stores yet" :ctaUrl="route('stores.create')" ctaLabel="Add one"/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>Merchant</th><th>Customer</th><th>Domain</th><th>Last sync</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
                    <tbody>
                    @foreach($stores as $s)
                        <tr>
                            <td>
                                <strong>{{ $s->dfid ? $s->dfid.' · ' : '' }}{{ $s->business_name ?? $s->name }}</strong>
                                <div style="font-size:11px;color:var(--a-text-3);font-family:ui-monospace,monospace;margin-top:2px">{{ $s->base_url }}</div>
                            </td>
                            <td>{{ $s->customer_name ?? '—' }}<div style="font-size:11px;color:var(--a-text-3)">{{ $s->customer_phone }}</div></td>
                            <td>{{ $s->domain_name ?? '—' }}</td>
                            <td>{{ optional($s->last_sync_at)->diffForHumans() ?? '—' }}</td>
                            <td>
                                @if($s->is_active)
                                    <span class="pill pill-active">Active</span>
                                @else
                                    <span class="pill pill-inactive">Inactive</span>
                                @endif
                            </td>
                            <td style="text-align:right;white-space:nowrap">
                                <div style="display:inline-flex;gap:4px;flex-wrap:nowrap">
                                    <form method="POST" action="{{ route('stores.ping', $s) }}">@csrf<button class="btn btn-outline btn-sm">Ping</button></form>
                                    <form method="POST" action="{{ route('stores.sync', $s) }}">@csrf<button class="btn btn-outline btn-sm">Sync</button></form>
                                    <a href="{{ route('stores.edit', $s) }}" class="btn btn-dark btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('stores.destroy', $s) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Delete</button></form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
