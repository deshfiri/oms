@php
    $u = auth()->user();
    $role = $u?->role ?? 'guest';
    $stores = \App\Models\Store::query()->where('is_active', true)->orderBy('name')->get(['id','name']);
    $activeStoreId = (int) session('active_store_id', $stores->first()->id ?? 0);
    $nav = [
        ['label'=>'Inbox',       'route'=>'inbox.index',     'icon'=>'📥', 'roles'=>['admin','picker','packer','dispatcher']],
        ['label'=>'Picking',     'route'=>'picking.index',   'icon'=>'🧺', 'roles'=>['admin','picker']],
        ['label'=>'Packing',     'route'=>'packing.index',   'icon'=>'📦', 'roles'=>['admin','packer']],
        ['label'=>'Dispatch',    'route'=>'dispatch.index',  'icon'=>'🚚', 'roles'=>['admin','dispatcher']],
        ['label'=>'Tracking',    'route'=>'tracking.index',  'icon'=>'📍', 'roles'=>['admin','dispatcher']],
        ['label'=>'Returns',     'route'=>'returns.index',   'icon'=>'↩️', 'roles'=>['admin','returns_clerk']],
        ['label'=>'Damages',     'route'=>'damages.index',   'icon'=>'⚠️', 'roles'=>['admin','damage_clerk']],
        ['label'=>'Inventory',   'route'=>'inventory.index', 'icon'=>'📚', 'roles'=>['admin','stock_counter']],
        ['label'=>'Stock count', 'route'=>'stock-counts.index','icon'=>'🔢','roles'=>['admin','stock_counter']],
        ['label'=>'Carts',       'route'=>'carts.index',     'icon'=>'🛒', 'roles'=>['admin']],
        ['label'=>'Reports',     'route'=>'reports.index',   'icon'=>'📊', 'roles'=>['admin']],
        ['label'=>'Stores',      'route'=>'stores.index',    'icon'=>'🏬', 'roles'=>['admin']],
        ['label'=>'Users',       'route'=>'users.index',     'icon'=>'🧑‍💼', 'roles'=>['admin']],
    ];
@endphp

<aside x-data="{open:false}" class="md:w-60 bg-slate-900 text-slate-100 md:min-h-screen flex flex-col">
    <div class="px-4 py-3 flex items-center justify-between border-b border-slate-700">
        <a href="{{ route('dashboard') }}" class="font-bold tracking-wide">DFOMS</a>
        <button class="md:hidden" @click="open=!open">☰</button>
    </div>

    <div class="px-3 py-2 border-b border-slate-700 hidden md:block">
        <form method="POST" action="{{ route('stores.switch') }}">
            @csrf
            <label class="text-xs uppercase text-slate-400">Active store</label>
            <select name="store_id" onchange="this.form.submit()" class="mt-1 w-full bg-slate-800 text-slate-100 text-sm rounded border-slate-700">
                @foreach($stores as $s)
                    <option value="{{ $s->id }}" @selected($s->id === $activeStoreId)>{{ $s->name }}</option>
                @endforeach
                @if($stores->isEmpty()) <option value="0">— no stores —</option> @endif
            </select>
        </form>
    </div>

    <nav :class="open ? 'block' : 'hidden md:block'" class="flex-1 py-2">
        @foreach($nav as $item)
            @php
                $allowed = $u && ($u->isAdmin() || in_array($u->role, $item['roles']));
                $exists  = Route::has($item['route']);
            @endphp
            @if($allowed && $exists)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-slate-800 {{ request()->routeIs($item['route']) ? 'bg-slate-800 border-l-4 border-emerald-400' : '' }}">
                    <span>{{ $item['icon'] }}</span><span>{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </nav>

    <div class="px-4 py-3 border-t border-slate-700 text-xs text-slate-400">
        <div>{{ $u?->name }}</div>
        <div class="uppercase tracking-wide">{{ str_replace('_',' ',$role) }}</div>
        <form method="POST" action="{{ route('logout') }}" class="mt-2">
            @csrf
            <button class="text-rose-300 hover:text-rose-200">Sign out</button>
        </form>
    </div>
</aside>
