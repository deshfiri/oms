<x-app-layout>
    @section('title', 'Customers')
    <div class="admin-page-header"><div><h1>Customer lookup</h1><p class="sub">Searches the mirror first; falls back to live API across every active store.</p></div></div>

    <form method="GET" class="admin-card" style="margin-bottom:14px">
        <div class="admin-card-body" style="display:flex;gap:8px">
            <input name="q" value="{{ request('q') }}" placeholder="Name, email or phone" autofocus class="input" style="flex:1">
            <button class="btn btn-dark">Search</button>
        </div>
    </form>

    <div class="admin-card">
        <div class="admin-card-head"><x-admin.section-head icon="user" title="Mirror results"/></div>
        @if($customers->isEmpty())
            <x-admin.empty icon="user" title="No mirror hits" description="Try a search — storefront results will appear below."/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>Name</th><th>Store</th><th>Email</th><th>Phone</th><th></th></tr></thead>
                    <tbody>
                    @foreach($customers as $c)
                        <tr>
                            <td>{{ $c->name }}</td>
                            <td style="font-size:12px"><strong>{{ optional($c->store)->dfid ?? '—' }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($c->store)->business_name ?? '' }}</div></td>
                            <td>{{ $c->email }}</td>
                            <td>{{ $c->phone }}</td>
                            <td style="text-align:right"><a href="{{ route('customers.show', $c) }}" class="btn btn-dark btn-sm">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if(!empty($remote))
        <div class="admin-card" style="margin-top:14px">
            <div class="admin-card-head"><x-admin.section-head icon="search" title="Storefront results (live)"/></div>
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>Name</th><th>Store</th><th>Email</th><th>Phone</th></tr></thead>
                    <tbody>
                    @foreach($remote as $row)
                        <tr>
                            <td>{{ $row['name'] ?? '' }}</td>
                            <td style="font-size:12px">{{ $row['_store'] ?? '' }}</td>
                            <td>{{ $row['email'] ?? '' }}</td>
                            <td>{{ $row['phone'] ?? '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
