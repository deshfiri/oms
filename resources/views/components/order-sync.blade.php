@props([
    'scope',       // poll.signature scope (verification / processing / packing / dispatch / tracking / returns)
    'rowsUrl',     // AJAX endpoint that returns {tbody,pagination,total} or {html}
    'mode'   => 'tbody',   // 'tbody' — replace <tbody> only | 'region' — replace a whole <div>
    'tbodyId'      => '',  // mode=tbody: <tbody id="...">
    'paginationId' => '',  // mode=tbody: <div id="..."> for pagination
    'totalId'      => '',  // mode=tbody: <span id="..."> for count (optional)
    'regionId'     => '',  // mode=region: <div id="..."> to replace entirely
])
{{-- Sync status bar — displayed inline at the component's position in the view --}}
<div id="oms-sync-bar" aria-live="polite"
     style="display:flex;align-items:center;gap:6px;font-size:11.5px;color:var(--a-text-2);padding:6px 0;opacity:0;transition:opacity .25s">
    <span id="oms-sync-spinner"
          style="display:none;width:11px;height:11px;border:2px solid var(--a-accent);border-top-color:transparent;border-radius:50%;flex-shrink:0;animation:oms-spin .7s linear infinite"></span>
    <span id="oms-sync-dot"
          style="width:7px;height:7px;border-radius:50%;background:var(--a-text-3);flex-shrink:0;transition:background .3s"></span>
    <span id="oms-sync-text"></span>
</div>

@push('head')
<style>@keyframes oms-spin { to { transform: rotate(360deg); } }</style>
@endpush

@push('scripts')
<script>
(function () {
    /* ── config ─────────────────────────────────────────────────────── */
    var POLL_MS  = 5000;
    var scope    = @json($scope);
    var rowsUrl  = @json($rowsUrl);
    var mode     = @json($mode);
    var tbodyId  = @json($tbodyId);
    var pagId    = @json($paginationId);
    var totalId  = @json($totalId);
    var regionId = @json($regionId);
    var sigUrl   = '{{ route('poll.signature') }}?scope=' + encodeURIComponent(scope);
    var csrf     = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';

    /* ── state ───────────────────────────────────────────────────────── */
    var lastSig = null;
    var busy    = false;
    var timer   = null;

    /* ── indicator ───────────────────────────────────────────────────── */
    var bar      = document.getElementById('oms-sync-bar');
    var spinner  = document.getElementById('oms-sync-spinner');
    var dot      = document.getElementById('oms-sync-dot');
    var txt      = document.getElementById('oms-sync-text');

    function pad(n) { return String(n).padStart(2, '0'); }
    function timeStr() {
        var d = new Date();
        return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }
    function show(syncing, ok) {
        if (!bar) return;
        bar.style.opacity = '1';
        spinner.style.display = syncing ? 'inline-block' : 'none';
        dot.style.display     = syncing ? 'none' : 'inline-block';
        if (!syncing) dot.style.background = ok ? '#22c55e' : '#ef4444';
        txt.textContent = syncing ? 'Syncing…' : (ok ? 'Last synced: ' + timeStr() : 'Sync error');
    }

    /* ── selection helpers ───────────────────────────────────────────── */
    function saveSelection(root) {
        return [...root.querySelectorAll('input[type=checkbox][value]:checked')].map(function (c) { return c.value; });
    }
    function restoreSelection(root, saved) {
        if (!saved.length) {
            // No prior selection — trigger one change to let the bulk-bar hide itself
            var any = root.querySelector('input[type=checkbox][name$="[]"]');
            if (any) any.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }
        var set = new Set(saved);
        root.querySelectorAll('input[type=checkbox][value]').forEach(function (cb) {
            if (set.has(cb.value)) cb.checked = true;
        });
        // Fire change on the first restored checkbox so bulk-bar refresh fires
        var first = root.querySelector('input[type=checkbox][value]:checked');
        if (first) first.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /* ── AJAX table refresh ──────────────────────────────────────────── */
    async function doRefresh() {
        // Never clobber an open dialog (e.g. inspection modal on Returns page)
        if (document.querySelector('dialog[open]')) return;

        var params  = new URLSearchParams(window.location.search);
        var url     = rowsUrl + (params.toString() ? '?' + params.toString() : '');
        var resp    = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
            cache: 'no-store',
        });
        if (!resp.ok) throw new Error('rows ' + resp.status);
        var data = await resp.json();

        if (mode === 'tbody' && tbodyId) {
            var tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            var saved = saveSelection(tbody.closest('form') || tbody);
            tbody.innerHTML = data.tbody ?? '';
            if (pagId && data.pagination != null) {
                var pag = document.getElementById(pagId);
                if (pag) pag.innerHTML = data.pagination;
            }
            if (totalId && data.total != null) {
                var tot = document.getElementById(totalId);
                if (tot) tot.textContent = data.total;
            }
            restoreSelection(tbody.closest('form') || tbody, saved);
        } else if (mode === 'region' && regionId) {
            var region = document.getElementById(regionId);
            if (!region) return;
            var saved = saveSelection(region);
            region.innerHTML = data.html ?? '';
            restoreSelection(region, saved);
        }
    }

    /* ── poll loop ───────────────────────────────────────────────────── */
    async function poll() {
        if (busy || document.hidden) return;
        busy = true;
        show(true);
        try {
            var r = await fetch(sigUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            if (!r.ok) throw new Error('sig ' + r.status);
            var j   = await r.json();
            var sig = j.sig ?? null;

            if (sig && lastSig !== null && sig !== lastSig) {
                await doRefresh();
            }
            lastSig = sig;
            show(false, true);
        } catch (e) {
            console.warn('[order-sync] ' + scope, e);
            show(false, false);
        } finally {
            busy = false;
        }
    }

    function start() { if (!timer) timer = setInterval(poll, POLL_MS); }
    function stop()  { clearInterval(timer); timer = null; }

    document.addEventListener('visibilitychange', function () {
        document.hidden ? stop() : (poll(), start());
    });
    window.addEventListener('beforeunload', stop);

    /* ── boot ────────────────────────────────────────────────────────── */
    poll();
    start();
})();
</script>
@endpush
