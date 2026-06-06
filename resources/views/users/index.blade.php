<x-app-layout>
    @section('title', 'Users')
    <div class="admin-page-header">
        <div><h1>Staff & roles</h1><p class="sub">{{ $users->total() }} user(s).</p></div>
        <a href="{{ route('users.create') }}" class="btn btn-dark">+ Add user</a>
    </div>
    <div class="admin-card">
        <div class="admin-card-head"><x-admin.section-head icon="shield" title="OMS staff"/></div>
        <div class="atable-wrap">
            <table class="atable">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Stores</th><th>Active</th><th></th></tr></thead>
                <tbody>
                @foreach($users as $u)
                    <tr>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td><x-admin.pill :status="$u->role" :label="ucwords(str_replace('_',' ',$u->role))"/></td>
                        <td style="font-size:12px">
                            @foreach($u->stores as $s)
                                <span class="pill pill-default">{{ $s->dfid ?: $s->business_name }}</span>
                            @endforeach
                            @if($u->stores->isEmpty()) <span style="color:var(--a-text-3)">—</span> @endif
                        </td>
                        <td>{{ $u->is_active ? '●' : '○' }}</td>
                        <td style="text-align:right;white-space:nowrap">
                            <div style="display:inline-flex;gap:4px">
                                <a href="{{ route('users.edit', $u) }}" class="btn btn-dark btn-sm">Edit</a>
                                <form method="POST" action="{{ route('users.destroy', $u) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Delete</button></form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="admin-pagination">{{ $users->links() }}</div>
    </div>
</x-app-layout>
