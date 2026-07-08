<x-app-layout>
    @section('title', $user->exists ? 'Edit user' : 'New user')
    <div class="admin-page-header"><div><h1>{{ $user->exists ? 'Edit user' : 'New user' }}</h1></div></div>
    @php $assigned = $user->stores->pluck('id')->all(); @endphp
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="admin-card" style="max-width:720px">
        @csrf
        @if($user->exists) @method('PATCH') @endif
        <div class="admin-card-body" style="display:flex;flex-direction:column;gap:10px">
            <label><span style="font-size:12px;font-weight:600">Name *</span><input name="name" value="{{ old('name', $user->name) }}" class="input" required></label>
            <label><span style="font-size:12px;font-weight:600">Email *</span><input name="email" value="{{ old('email', $user->email) }}" class="input" required></label>
            <div>
                <span style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">Roles * <span style="font-size:11px;color:var(--a-text-3);font-weight:400">(select one or more)</span></span>
                @php $currentRoles = old('roles', $user->roles ?? ($user->role ? [$user->role] : [])); @endphp
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:4px 16px;padding:10px 12px;border:1px solid var(--a-border);border-radius:var(--a-r);background:var(--a-surface-2)">
                    @foreach(\App\Models\User::ROLES as $roleName)
                        <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer;padding:3px 0">
                            <input type="checkbox" name="roles[]" value="{{ $roleName }}"
                                   @checked(in_array($roleName, (array) $currentRoles))
                                   style="width:15px;height:15px;accent-color:var(--a-accent)">
                            {{ ucwords(str_replace('_', ' ', $roleName)) }}
                        </label>
                    @endforeach
                </div>
                @error('roles')<span style="font-size:11px;color:#dc2626">{{ $message }}</span>@enderror
            </div>
            <label><span style="font-size:12px;font-weight:600">Assigned stores</span>
                <select name="store_ids[]" multiple size="6" class="input" style="height:auto">
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" @selected(in_array($s->id, $assigned))>{{ ($s->dfid?$s->dfid.' · ':'').($s->business_name ?? $s->name) }}</option>
                    @endforeach
                </select>
                <span style="font-size:11px;color:var(--a-text-3)">Hold ⌘ / Ctrl to select multiple. Social-media-manager users can only place orders for stores listed here.</span>
            </label>
            <label><span style="font-size:12px;font-weight:600">Password {{ $user->exists ? '(blank = keep)' : '*' }}</span><input name="password" type="password" class="input" @if(!$user->exists) required @endif></label>
            <label><span style="font-size:12px;font-weight:600">Confirm password</span><input name="password_confirmation" type="password" class="input" @if(!$user->exists) required @endif></label>
            @if($user->exists)
                <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" name="is_active" value="1" @checked($user->is_active)> Active</label>
            @endif
            <div style="display:flex;gap:8px"><button class="btn btn-dark">Save</button><a href="{{ route('users.index') }}" class="btn btn-outline">Cancel</a></div>
        </div>
    </form>
</x-app-layout>
