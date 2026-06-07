@php
    use App\Services\Orders\OrderStateMachine as S;
    $qs = fn(array $over = []) => '?' . http_build_query(array_merge(request()->except('page'), $over));
    $activeStatuses = collect(is_array(request('status')) ? request('status') : array_filter(explode(',', (string) request('status'))));
@endphp
<x-app-layout>
    @section('title', 'All Orders')

    @push('head')
    <style>
        @keyframes orders-spin { to { transform: rotate(360deg); } }
        .orders-sync-spinner { display:inline-block;width:11px;height:11px;border:2px solid var(--a-accent);border-top-color:transparent;border-radius:50%;animation:orders-spin .7s linear infinite;vertical-align:middle }
    </style>
    @endpush

    <div class="admin-page-header">
        <div>
            <h1>All Orders</h1>
            <p class="sub">{{ number_format($totalCount) }} order(s) match the current filters across every store.</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
            {{-- Sync status indicator --}}
            <span id="orders-sync-indicator" style="display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--a-text-2);opacity:0;transition:opacity .25s">
                <span id="orders-sync-spinner" class="orders-sync-spinner" style="display:none"></span>
                <span id="orders-sync-dot" style="width:7px;height:7px;border-radius:50%;background:var(--a-text-3);flex-shrink:0;transition:background .3s"></span>
                <span id="orders-sync-text"></span>
            </span>
            <a href="{{ route('orders.export', request()->except('page')) }}" class="btn btn-outline">Export CSV</a>
        </div>
    </div>

    {{-- ── Advanced filter bar ───────────────────────────────────────── --}}
    <form method="GET" class="admin-card" style="margin-bottom:14px">
        <div class="admin-card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <label style="flex:1;min-width:240px">
                <span style="font-size:11px;color:var(--a-text-2)">Search</span>
                <input class="input" name="q" value="{{ request('q') }}" placeholder="Order # · name · phone · email · tracking · consignment · SKU">
            </label>
            <label><span style="font-size:11px;color:var(--a-text-2)">Store</span>
                <select class="input" name="store_id" style="width:auto">
                    <option value="">All</option>
                    @foreach($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id')==$s->id)>{{ ($s->dfid?$s->dfid.' · ':'').($s->business_name ?? $s->name) }}</option>@endforeach
                </select>
            </label>
            <label><span style="font-size:11px;color:var(--a-text-2)">Payment</span>
                <select class="input" name="payment_method" style="width:auto">
                    <option value="">Any</option>
                    @foreach(['cod','bkash','nagad','card','bank_transfer'] as $pm)<option value="{{ $pm }}" @selected(request('payment_method')===$pm)>{{ ucfirst(str_replace('_',' ',$pm)) }}</option>@endforeach
                </select>
            </label>
            <label><span style="font-size:11px;color:var(--a-text-2)">Courier</span>
                <select class="input" name="courier" style="width:auto">
                    <option value="">Any</option>
                    @foreach($couriers as $cs)<option value="{{ $cs }}" @selected(request('courier')===$cs)>{{ ucfirst($cs) }}</option>@endforeach
                </select>
            </label>
            <label><span style="font-size:11px;color:var(--a-text-2)">Zone</span>
                <select class="input" name="zone" style="width:auto">
                    <option value="">Any</option>
                    @foreach(['inside_dhaka'=>'Inside Dhaka','outside_dhaka'=>'Outside Dhaka','sub_city'=>'Sub-city'] as $zk=>$zv)<option value="{{ $zk }}" @selected(request('zone')===$zk)>{{ $zv }}</option>@endforeach
                </select>
            </label>
            <label><span style="font-size:11px;color:var(--a-text-2)">Source</span>
                <select class="input" name="source" style="width:auto">
                    <option value="">Any</option>
                    <option value="storefront" @selected(request('source')==='storefront')>Storefront</option>
                    <option value="oms" @selected(request('source')==='oms')>OMS-placed</option>
                </select>
            </label>
            <label><span style="font-size:11px;color:var(--a-text-2)">From</span><input class="input" type="date" name="from" value="{{ request('from') }}"></label>
            <label><span style="font-size:11px;color:var(--a-text-2)">To</span><input class="input" type="date" name="to" value="{{ request('to') }}"></label>
            {{-- keep current status filter when applying other filters --}}
            @foreach($activeStatuses as $st)<input type="hidden" name="status[]" value="{{ $st }}">@endforeach
            <button class="btn btn-dark">Apply</button>
            @if(request()->hasAny(['q','store_id','payment_method','courier','zone','source','from','to','status']))
                <a href="{{ route('orders.index') }}" class="btn btn-ghost">Clear</a>
            @endif
        </div>
    </form>

    {{-- ── Live region: status chips + results table ────────────────── --}}
    {{-- This div is replaced in-place by the AJAX poller below.        --}}
    <div id="orders-live-region">
        @include('orders._table')
    </div>

    @push('scripts')
    <script>
    (function () {
        var POLL_MS    = 5000;
        var sigUrl     = '{{ route('poll.signature') }}?scope=orders';
        var rowsBase   = '{{ route('orders.rows') }}';

        var region     = document.getElementById('orders-live-region');
        var indicator  = document.getElementById('orders-sync-indicator');
        var spinner    = document.getElementById('orders-sync-spinner');
        var dot        = document.getElementById('orders-sync-dot');
        var statusText = document.getElementById('orders-sync-text');

        var lastSig  = null;
        var busy     = false;
        var timer    = null;

        function pad(n) { return String(n).padStart(2, '0'); }
        function timeStr() {
            var d = new Date();
            return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
        }

        function showSyncing() {
            indicator.style.opacity = '1';
            spinner.style.display   = 'inline-block';
            dot.style.display       = 'none';
            dot.style.background    = 'var(--a-accent)';
            statusText.textContent  = 'Syncing…';
        }

        function showDone() {
            spinner.style.display  = 'none';
            dot.style.display      = 'inline-block';
            dot.style.background   = '#22c55e';
            statusText.textContent = 'Last synced: ' + timeStr();
            indicator.style.opacity = '1';
        }

        function showError() {
            spinner.style.display  = 'none';
            dot.style.display      = 'inline-block';
            dot.style.background   = '#ef4444';
            statusText.textContent = 'Sync failed';
            indicator.style.opacity = '1';
        }

        async function poll() {
            if (busy || document.hidden) return;
            busy = true;
            showSyncing();

            try {
                // Step 1 — get change signature (also triggers throttled storefront pull).
                var sigResp = await fetch(sigUrl, {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });
                if (!sigResp.ok) throw new Error('sig ' + sigResp.status);
                var sigJson = await sigResp.json();
                var sig = sigJson.sig || null;

                // Step 2 — refresh table only when something changed.
                if (sig && lastSig !== null && sig !== lastSig) {
                    var params = new URLSearchParams(window.location.search);
                    var rowsUrl = rowsBase + (params.toString() ? '?' + params.toString() : '');

                    var rowsResp = await fetch(rowsUrl, {
                        headers: {
                            'Accept': 'text/html',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        cache: 'no-store'
                    });
                    if (!rowsResp.ok) throw new Error('rows ' + rowsResp.status);

                    var html = await rowsResp.text();
                    region.innerHTML = html;
                }

                lastSig = sig;
                showDone();
            } catch (e) {
                console.warn('[orders-sync] poll failed', e);
                showError();
            } finally {
                busy = false;
            }
        }

        function startTimer() {
            if (timer) return;
            timer = setInterval(poll, POLL_MS);
        }

        function stopTimer() {
            clearInterval(timer);
            timer = null;
        }

        // Pause polling when tab is hidden; resume (with immediate poll) when visible.
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopTimer();
            } else {
                poll();
                startTimer();
            }
        });

        // Clean up on navigation.
        window.addEventListener('beforeunload', stopTimer);

        // Kick off immediately on page load.
        poll();
        startTimer();
    })();
    </script>
    @endpush
</x-app-layout>
