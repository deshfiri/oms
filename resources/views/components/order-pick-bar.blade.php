{{-- Floating totals for the orders ticked in any order list (<x-order-pick>), with a
     "Details" panel listing every selected product and its total quantity.
     Rendered once by the layout; styles: .opick-* in public/css/oms.css --}}
<div class="opick" id="order-pick-bar" hidden>
    <div class="opick-panel" id="opick-panel" hidden>
        <div class="opick-panel-head">
            <strong>Selected products</strong>
            <span data-opick="panel-sub"></span>
        </div>
        <ul class="opick-plist" data-opick="plist"></ul>
    </div>

    <div class="opick-bar" role="status" aria-live="polite">
        <span class="opick-badge" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </span>
        <div class="opick-stat"><strong data-opick="orders">0</strong><span data-opick="orders-label">Orders</span></div>
        <div class="opick-stat"><strong data-opick="products">0</strong><span data-opick="products-label">Products</span></div>
        <div class="opick-stat"><strong data-opick="pcs">0</strong><span data-opick="pcs-label">Pieces</span></div>
        <div class="opick-actions">
            <button type="button" class="opick-btn" data-opick-toggle aria-expanded="false" aria-controls="opick-panel">
                Details
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
            </button>
            <button type="button" class="opick-btn opick-btn-icon" data-opick-clear title="Clear selection" aria-label="Clear selection">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
</div>
<script>
(function () {
    var root = document.getElementById('order-pick-bar');
    if (!root) return;
    var panel  = document.getElementById('opick-panel');
    var toggle = root.querySelector('[data-opick-toggle]');
    var out = {};
    root.querySelectorAll('[data-opick]').forEach(function (el) { out[el.dataset.opick] = el; });
    var queued = false;

    // Header checkboxes follow their table: checked when all rows are, dash when some are.
    function syncHeaders() {
        document.querySelectorAll('input[data-order-pick-all]').forEach(function (all) {
            var table = all.closest('table');
            if (!table) return;
            var total = table.querySelectorAll('input[data-order-pick]').length;
            var on    = table.querySelectorAll('input[data-order-pick]:checked').length;
            all.checked = total > 0 && on === total;
            all.indeterminate = on > 0 && on < total;
        });
    }

    // Set a number and give it a small bump when it changes.
    function setNum(el, n) {
        if (el.textContent === String(n)) return;
        el.textContent = n;
        el.classList.remove('bump');
        void el.offsetWidth;
        el.classList.add('bump');
    }

    function renderList(products) {
        var list = out.plist;
        list.textContent = '';
        products.forEach(function (p) {
            var li = document.createElement('li');
            var qty = document.createElement('span');
            qty.className = 'oi-qty';
            qty.textContent = p.qty + '×';
            var name = document.createElement('span');
            name.className = 'opick-pname';
            name.textContent = p.name;
            name.title = p.name + (p.variant ? ' · ' + p.variant : '');
            if (p.variant) {
                var v = document.createElement('small');
                v.textContent = p.variant;
                name.appendChild(v);
            }
            var orders = document.createElement('span');
            orders.className = 'opick-porders';
            orders.textContent = p.orders + (p.orders === 1 ? ' order' : ' orders');
            li.append(qty, name, orders);
            list.appendChild(li);
        });
    }

    function update() {
        queued = false;
        var orders = 0, pcs = 0, byKey = {};
        document.querySelectorAll('input[data-order-pick]:checked').forEach(function (cb) {
            orders++;
            pcs += Number(cb.dataset.pcs) || 0;
            var items = [];
            try { items = JSON.parse(cb.dataset.items || '[]'); } catch (e) {}
            items.forEach(function (it) {
                var key = it[0] + '\u0000' + (it[1] || '');
                var p = byKey[key] || (byKey[key] = { name: it[0], variant: it[1], qty: 0, orders: 0 });
                p.qty += Number(it[2]) || 0;
                p.orders++;
            });
        });
        var products = Object.values(byKey).sort(function (a, b) { return b.qty - a.qty || a.name.localeCompare(b.name); });

        setNum(out.orders, orders);
        setNum(out.products, products.length);
        setNum(out.pcs, pcs);
        out['orders-label'].textContent   = orders === 1 ? 'Order' : 'Orders';
        out['products-label'].textContent = products.length === 1 ? 'Product' : 'Products';
        out['pcs-label'].textContent      = pcs === 1 ? 'Piece' : 'Pieces';
        out['panel-sub'].textContent = products.length + ' unique · ' + pcs + ' pcs';
        renderList(products);

        root.hidden = orders === 0;
        if (!orders) setPanel(false);
        syncHeaders();
    }
    // Deferred so page-specific "select all" handlers finish ticking rows first.
    function schedule() { if (!queued) { queued = true; setTimeout(update, 0); } }

    function setPanel(open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        root.classList.toggle('is-open', open);
    }

    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!(t instanceof HTMLInputElement) || t.type !== 'checkbox') return;
        if (t.hasAttribute('data-order-pick-all')) {
            var table = t.closest('table');
            if (table) table.querySelectorAll('input[data-order-pick]').forEach(function (cb) { cb.checked = t.checked; });
        }
        schedule();
    });

    toggle.addEventListener('click', function () { setPanel(panel.hidden); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) setPanel(false); });

    root.querySelector('[data-opick-clear]').addEventListener('click', function () {
        document.querySelectorAll('input[data-order-pick]:checked').forEach(function (cb) {
            cb.checked = false;
            cb.dispatchEvent(new Event('change', { bubbles: true }));   // lets page bulk bars refresh too
        });
    });

    // Tables re-rendered by the AJAX pollers: recount what is still on the page.
    new MutationObserver(schedule).observe(document.querySelector('.admin-content') || document.body, { childList: true, subtree: true });
    schedule();
})();
</script>
