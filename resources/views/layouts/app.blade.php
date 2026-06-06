@php
    use App\View\Icons;

    $r = optional(request()->route())->getName() ?? '';
    $u = auth()->user();

    // Sidebar sections — OMS verbs grouped to feel like DFCOMMERCE's admin.
    $sections = [
        null => [
            ['dashboard',      'Dashboard',      Icons::svg('dashboard'), null, null],
            ['orders.index',   'All Orders',     Icons::svg('list'),      null, ['admin','customer_support','warehouse_admin','dispatcher','returns_clerk']],
            ['reports.index',  'Reports',        Icons::svg('reports'),   null, ['admin']],
        ],
        'Sales' => [
            ['orders-new.index',  'My orders', Icons::svg('cart'),      null, ['admin','social_media_manager']],
            ['orders-new.create', 'Register order', Icons::svg('cart-plus'), null, ['admin','social_media_manager']],
        ],
        'Customer support' => [
            ['verification.index', 'Pending Order', Icons::svg('clock'), 'verification_due', ['admin','customer_support']],
        ],
        'Warehouse' => [
            ['processing.index', 'Processing', Icons::svg('clipboard'), 'processing_queue', ['admin','warehouse_admin','packer']],
            ['packing.index',    'Packing',    Icons::svg('package'),   'packing_subq',     ['admin','warehouse_admin','packer']],
        ],
        'Dispatch & Tracking' => [
            ['dispatch.index',  'Dispatch', Icons::svg('truck'), 'dispatch_due', ['admin','dispatcher']],
            ['tracking.index',  'Tracking', Icons::svg('pin'),   null,           ['admin','dispatcher']],
        ],
        'Returns & Aftercare' => [
            ['returns.index',    'Returns',   Icons::svg('rotate'),  'returns_open', ['admin','returns_clerk']],
            ['exchanges.index',  'Exchanges', Icons::svg('swap'),    null,           ['admin','returns_clerk','customer_support']],
            ['damages.index',    'Damages',   Icons::svg('warning'), null,           ['admin','damage_clerk','returns_clerk']],
            ['lost.index',       'Lost',      Icons::svg('help'),    null,           ['admin','warehouse_admin','dispatcher']],
        ],
        'Inventory' => [
            ['inventory.index',    'Inventory',    Icons::svg('inventory'), 'inventory_low', ['admin','inventory_admin','stock_counter']],
            ['stock-counts.index', 'Stock Counts', Icons::svg('list'),      null,            ['admin','inventory_admin','stock_counter']],
        ],
        'System' => [
            ['stores.index', 'Stores',        Icons::svg('store'),  null, ['admin']],
            ['users.index',  'Staff & Roles', Icons::svg('shield'), null, ['admin']],
        ],
    ];

    // Live badge counts across ALL stores (cached 30s)
    $badges = \Illuminate\Support\Facades\Cache::remember(
        'badges.all.'.($u?->id ?? 0),
        30,
        fn () => [
            'verification_due'  => \App\Models\OrderMirror::where('status', \App\Services\Orders\OrderStateMachine::PENDING_VERIFICATION)->count(),
            'processing_queue'  => \App\Models\OrderMirror::where('status', \App\Services\Orders\OrderStateMachine::PROCESSING)->whereDoesntHave('consignments')->count(),
            'packing_subq'      => \App\Models\OrderMirror::where('status', \App\Services\Orders\OrderStateMachine::PROCESSING)->whereHas('consignments')->count(),
            'dispatch_due'      => \App\Models\OrderMirror::where('status', \App\Services\Orders\OrderStateMachine::PACKED)->count(),
            'returns_open'      => \App\Models\OrderMirror::whereIn('status', [
                                        \App\Services\Orders\OrderStateMachine::RETURN_PENDING,
                                        \App\Services\Orders\OrderStateMachine::RETURNED,
                                   ])->count(),
            'inventory_low'     => \App\Models\ProductMirror::whereColumn('stock_quantity','<=','low_stock_threshold')->where('manage_stock', true)->count(),
        ],
    );

    $active = function (string $route) use ($r) {
        if ($route === 'dashboard') return $r === 'dashboard';
        $base = str_replace('.index', '', $route);
        return str_starts_with($r, $base);
    };

    $storesCount = \App\Models\Store::where('is_active', true)->count();
    $initials = collect(explode(' ', $u->name ?? 'A'))->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $title ?? 'Dashboard') — DFOMS</title>
    <link rel="icon" type="image/png" href="{{ asset('favwh.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('favwh.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favwh.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @php
        $cssV = @filemtime(public_path('css/admin.css')) ?: time();
        $omsV = @filemtime(public_path('css/oms.css')) ?: time();
    @endphp
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ $cssV }}">
    <link rel="stylesheet" href="{{ asset('css/oms.css') }}?v={{ $omsV }}">

    {{-- Critical fallback — only used if admin.css fails to load; the @media block
         mirrors admin.css's so the sidebar still drawers on mobile in that case. --}}
    <style>
        :root{--a-bg:#f5f6f8;--a-surface:#fff;--a-surface-2:#f9fafb;--a-border:#e5e7eb;--a-text:#0d0d0d;--a-text-2:#6b7280;--a-text-3:#9ca3af;--a-sidebar-bg:#fff;--a-sidebar-text:#374151;--a-sidebar-text-2:#9ca3af;--a-r:8px;--a-r-lg:16px;--a-sidebar-w:244px;--a-topbar-h:60px;--brand-rgb:17,146,100;--a-accent:#119264;--a-accent-hover:#0d7650;--a-accent-soft:rgba(17,146,100,.08);--a-accent-border:rgba(17,146,100,.20)}
        html,body{margin:0;padding:0}
        .admin-body{background:var(--a-bg);color:var(--a-text);font-family:'Poppins',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:14px;line-height:1.5;min-height:100vh;-webkit-font-smoothing:antialiased}
        .admin-body *,.admin-body *::before,.admin-body *::after{box-sizing:border-box}
        .admin-shell{display:flex;min-height:100vh}
        .admin-sidebar{width:var(--a-sidebar-w);background:var(--a-sidebar-bg);color:var(--a-sidebar-text);flex-shrink:0;position:fixed;top:0;bottom:0;left:0;z-index:300;display:flex;flex-direction:column;transition:transform .28s cubic-bezier(.4,0,.2,1);border-right:1px solid var(--a-border)}
        .admin-main{flex:1;margin-left:var(--a-sidebar-w);min-width:0;display:flex;flex-direction:column}
        .admin-topbar{background:var(--a-surface);border-bottom:1px solid var(--a-border);height:var(--a-topbar-h);display:flex;align-items:center;gap:16px;padding:0 28px;position:sticky;top:0;z-index:200;box-shadow:0 1px 3px rgba(0,0,0,.02)}
        .admin-content{padding:24px;flex:1}
        @media(max-width:900px){
            .admin-sidebar{transform:translateX(-100%)}
            .admin-sidebar.open{transform:translateX(0)}
            .admin-main{margin-left:0}
            .admin-topbar{padding:0 14px}
            .admin-content{padding:14px}
        }
    </style>
    @stack('head')
</head>
<body class="admin-body">
    <div class="admin-shell">
        <div class="admin-backdrop" id="adminBackdrop" onclick="closeAdminSidebar()"></div>

        {{-- ── SIDEBAR ──────────────────────────────────────────── --}}
        <aside class="admin-sidebar" id="adminSidebar" aria-label="OMS navigation">
            <div class="admin-sidebar-header">
                <a href="{{ route('dashboard') }}" class="admin-sidebar-brand" style="display:inline-flex;align-items:center;text-decoration:none">
                    <img src="{{ asset('omslogo2.png') }}" alt="OMS Logo" style="height:40px;max-width:150px;object-fit:contain">
                </a>
                <button type="button" class="admin-sidebar-close" onclick="closeAdminSidebar()" aria-label="Close menu">{!! Icons::svg('x') !!}</button>
            </div>

            <nav class="admin-nav">
                @foreach($sections as $heading => $items)
                    @if($heading)<div class="admin-nav-section">{{ $heading }}</div>@endif
                    @foreach($items as $item)
                        @php
                            [$route, $label, $icon, $badgeKey, $allowedRoles] = $item;
                            if ($allowedRoles && $u && ! $u->isAdmin() && ! in_array($u->role, $allowedRoles, true)) continue;
                            if (! \Illuminate\Support\Facades\Route::has($route)) continue;
                            $url      = route($route);
                            $isActive = $active($route);
                            $count    = $badgeKey ? ($badges[$badgeKey] ?? 0) : 0;
                            $alert    = in_array($badgeKey, ['orders_inbox','returns_open','dispatch_due'], true);
                        @endphp
                        <a href="{{ $url }}" class="admin-nav-item {{ $isActive ? 'active' : '' }} {{ ($alert && $count) ? 'has-alert' : '' }}">
                            {!! $icon !!}
                            <span>{{ $label }}</span>
                            @if($count > 0)<span class="badge">{{ $count }}</span>@endif
                        </a>
                    @endforeach
                @endforeach
            </nav>
        </aside>

        {{-- ── MAIN ─────────────────────────────────────────────── --}}
        <div class="admin-main">
            <header class="admin-topbar">
                <button type="button" class="admin-burger" onclick="openAdminSidebar()" aria-label="Open menu">{!! Icons::svg('menu') !!}</button>
                <h1 class="admin-page-title">@yield('title', $title ?? 'Dashboard')</h1>

                <form action="{{ route('inbox.index') }}" method="GET" class="admin-search-bar" role="search">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search orders by number, name, phone…" autocomplete="off">
                    <button type="submit" class="admin-search-btn" aria-label="Search">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    </button>
                </form>

                <div class="admin-topbar-right">
                    <span style="font-size:11px;color:var(--a-text-2);font-weight:600;padding:6px 12px;background:var(--a-surface-2);border-radius:var(--a-r);white-space:nowrap">{{ $storesCount }} store{{ $storesCount === 1 ? '' : 's' }}</span>
                    <span class="admin-user-chip">
                        <span class="avatar">{{ $initials }}</span>
                        <span class="name">{{ $u?->name }}</span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}" style="margin:0">
                        @csrf
                        <button type="submit" class="admin-iconbtn" title="Sign out" aria-label="Sign out">{!! Icons::svg('logout') !!}</button>
                    </form>
                </div>
            </header>

            <main class="admin-content">
                @if(session('status'))<div class="admin-flash admin-flash-ok">{{ session('status') }}</div>@endif
                @if(session('warning'))<div class="admin-flash admin-flash-warn">{{ session('warning') }}</div>@endif
                @if(session('error'))<div class="admin-flash admin-flash-err">{{ session('error') }}</div>@endif
                @if($errors->any())<div class="admin-flash admin-flash-err">{{ $errors->first() }}</div>@endif
                <style>.admin-flash-warn{background:#fef3c7;border:1px solid #fcd34d;color:#92400e}</style>

                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        window.openAdminSidebar = () => { document.getElementById('adminSidebar').classList.add('open'); document.getElementById('adminBackdrop').classList.add('open'); document.body.style.overflow = 'hidden'; };
        window.closeAdminSidebar = () => { document.getElementById('adminSidebar').classList.remove('open'); document.getElementById('adminBackdrop').classList.remove('open'); document.body.style.overflow = ''; };
        document.querySelectorAll('.admin-sidebar .admin-nav-item').forEach(a => a.addEventListener('click', () => {
            if (window.matchMedia('(max-width:900px)').matches) closeAdminSidebar();
        }));
    </script>
    @stack('scripts')
</body>
</html>
