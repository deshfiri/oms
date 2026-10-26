# OMS — 3 ta notun feature er shob change (merge guide)

Source: branch `purba`, commit `0e0b9ec` ("Order lists: store search, items with quantity and order selection").
Base: `main` (`d8985fd`). Ei file e `main` theke `purba` porjonto **shob** change ache — mot 44 ta file (37 ta app/view/css + 7 ta test).

## 3 ta feature

1. **Store search** — prottek order list e (All Orders, Inbox, My orders, Verification, Processing, Packing, Dispatch, Returns, Refunds) ekta free-text store search box. Store ID (DFID), business name ba name diye khoja jay. URL param `?store=`; purono `?store_id=` link o kaj kore.
2. **Products × quantity** — order list, dashboard ar customer page e prottek order er product ar quantity dekhay (`<x-order-items>`). Items ek query te eager-load hoy.
3. **Order select (pick)** — prottek order row te checkbox (`<x-order-pick>`). Niche ekta floating bar e selected order, product ar piece er total dekhay, ar "Details" panel e shob selected product er list.

## Ja change HOY NAI

- `database/migrations/` — kono change nai, **`php artisan migrate` lagbe na**
- `database/seeders/` — kono change nai
- `routes/`, `config/`, `.env`, `composer.json`, `package.json` — kono change nai
- Notun kono package / JS build nai (CSS plain file `public/css/oms.css`)

Je DB column gulo lage, segulo age thekei migration e ache:
`stores.dfid`, `stores.business_name`, `stores.name` ar `order_items_mirror.name`, `variant_label`, `qty`.
Live DB te ei column gulo ache kina ekbar check kore niben.

## Live code e merge korar niyom

1. Live zip unzip korun. Shuru korar age oi folder er ekta backup rakhun.
2. **Notun file (Section A)** — 5 ta Blade component. Ei file gulo live e nai, tai puro code copy kore same path e notun file banan.
3. **Edit kora file (Section B)** — 14 ta PHP/CSS file ar 18 ta view. Ekhane `diff` deya ache:
   - `-` diye shuru line = live file theke **muche dite hobe**
   - `+` diye shuru line = **add korte hobe**
   - space diye shuru line = context (ager motoi thakbe, khuje pawar jonno)
   - `@@ -75,7 +75,7 @@` = repository te ager file er moto line number. Live file e line number alada hote pare, tai context line diye jayga khujun.
   - Live file jodi repository r `main` er theke alada hoy (onno lok er extra change), tahole shudhu `-`/`+` line gulo hate boshaben. Live er nijer extra code muchben na.
4. **Test file (Section C)** — optional. Live server e test lage na. Repository te test chalate chaile copy korben.
5. Merge shesh hole:
   ```
   php artisan view:clea
   php artisan cache:clea
   ```
   Browser e `Ctrl+F5` diye CSS reload korun.

> **Shortcut (jodi live folder e git thake ar file gula `main` er moto hoy):**
> repository theke `git diff main..purba > oms-3-features.patch` banan, live folder e `git apply --3way oms-3-features.patch` chalan.
> Conflict hole sheta Section B dekhe hate thik korben.

### Jekhane conflict hote pare

Ei 4 ta file "warehouse update" commit (`1723b71`) eo change hoyechilo. Live e jodi oi update er alada version thake, tahole ei 4 ta file khub kheyal kore merge korben:
`resources/views/layouts/app.blade.php`, `app/Http/Controllers/VerificationController.php`, `resources/views/dispatch/_groups.blade.php`, `resources/views/packing/_rows.blade.php`.

## Shob file er list (checklist)

### A. Notun file (5)

| # | File | Feature | Ki kore |
|---|---|---|---|
| A1 | `resources/views/components/store-search.blade.php` | Store search | Search box + store suggestion dropdown |
| A2 | `resources/views/components/order-items.blade.php` | Products × qty | Order er product × quantity cell |
| A3 | `resources/views/components/order-pick.blade.php` | Order select | Row er checkbox |
| A4 | `resources/views/components/order-pick-all.blade.php` | Order select | Header er "shob select" checkbox |
| A5 | `resources/views/components/order-pick-bar.blade.php` | Order select | Floating total bar + Details panel (JS shoho) |

### B. Edit kora file (32)

| # | File | Feature | Ki change |
|---|---|---|---|
| B1 | `app/Models/OrderMirror.php` | Search, Items | `ITEM_COLUMNS` constant + `scopeStoreFilter()` |
| B2 | `app/Models/Store.php` | Search | `searchWords()`, `scopeMatching()`, `forPicker()` |
| B3 | `app/Http/Controllers/AllOrdersController.php` | Search, Items | items eager-load, `storeFilter`, `Store::forPicker()` |
| B4 | `app/Http/Controllers/CustomerController.php` | Items | items eager-load |
| B5 | `app/Http/Controllers/DashboardController.php` | Items | items eager-load |
| B6 | `app/Http/Controllers/DispatchController.php` | Search, Items | items eager-load, `storeFilter` (index, rows, CSV) |
| B7 | `app/Http/Controllers/InboxController.php` | Search, Items | items eager-load, `storeFilter`, `forPicker()` |
| B8 | `app/Http/Controllers/OrderCreationController.php` | Search, Items | items eager-load, `storeFilter` |
| B9 | `app/Http/Controllers/PackingController.php` | Search, Items | items eager-load, `storeFilter`, `forPicker()` |
| B10 | `app/Http/Controllers/ProcessingController.php` | Search, Items | items eager-load, `storeFilter`, `forPicker()` |
| B11 | `app/Http/Controllers/RefundController.php` | Search, Items | items eager-load, `storeFilter` (list + counts) |
| B12 | `app/Http/Controllers/ReturnsController.php` | Search, Items | items eager-load, `storeFilter` (list + counts) |
| B13 | `app/Http/Controllers/VerificationController.php` | Search, Items | items eager-load, `storeFilter`, `forPicker()` |
| B14 | `public/css/oms.css` | Shob 3 ta | `.oi-*`, `.store-combo*`, `.opick*` style (file er sheshe add) |
| B15 | `resources/views/layouts/app.blade.php` | Select | `</body>` er age `<x-order-pick-bar/>` |
| B16 | `resources/views/dashboard.blade.php` | Items, Select | Checkbox + Items column |
| B17 | `resources/views/customers/show.blade.php` | Items, Select | Checkbox + Items column |
| B18 | `resources/views/orders/all.blade.php` | Search, Select | Store dropdown → `<x-store-search>`, AJAX refresh e tick dhore rakha |
| B19 | `resources/views/orders/_table.blade.php` | Items, Select | Checkbox + Items column |
| B20 | `resources/views/orders-new/index.blade.php` | Search, Items, Select | Store search + checkbox + Items column |
| B21 | `resources/views/inbox/index.blade.php` | Search, Items, Select | Store dropdown → search, checkbox + Items column |
| B22 | `resources/views/verification/index.blade.php` | Search, Items, Select | Store dropdown → search, header checkbox + Items header |
| B23 | `resources/views/verification/_rows.blade.php` | Items, Select | Row checkbox → `<x-order-pick>`, Items cell |
| B24 | `resources/views/processing/index.blade.php` | Search, Select | Store dropdown → search, header checkbox |
| B25 | `resources/views/processing/_rows.blade.php` | Items, Select | Row checkbox → `<x-order-pick>`, qty sum → `<x-order-items>` |
| B26 | `resources/views/packing/index.blade.php` | Search, Items, Select | Store dropdown → search, header checkbox + Items header |
| B27 | `resources/views/packing/_rows.blade.php` | Items, Select | Row checkbox → `<x-order-pick>`, Items cell |
| B28 | `resources/views/dispatch/index.blade.php` | Search | Store search form, CSV link e filter |
| B29 | `resources/views/dispatch/_groups.blade.php` | Search, Items, Select | CSV link e filter, checkbox → component, Items column |
| B30 | `resources/views/returns/index.blade.php` | Search | Store search form |
| B31 | `resources/views/returns/_region.blade.php` | Search, Items, Select | Chip link e store rakha, checkbox + Items column |
| B32 | `resources/views/refunds/index.blade.php` | Search, Items, Select | Store search form, chip link, checkbox + Items column |

### C. Test file (7, optional)

`tests/Feature/AllOrdersItemsColumnTest.php`, `tests/Feature/AllOrdersStoreSearchTest.php`, `tests/Feature/OrderListPagesTest.php`, `tests/Feature/OrderPickTest.php`, `tests/Feature/StoreFilterScopeTest.php`, `tests/Unit/OrderComponentsTest.php`, `tests/Unit/StoreSearchWordsTest.php`

---

## Section A — Notun file (puro code copy korun)

### A1. `resources/views/components/store-search.blade.php`

Notun file. Ei path e banan, niche er puro code boshan.

````blade
@props([
    'stores' => null,          // Store collection; loaded via Store::forPicker() when omitted
    'name'   => 'store',       // query param read by OrderMirror::storeFilter()
    'width'  => '240px',
    'placeholder' => 'Search store ID or name',
])
{{--
    Free-text store search. Whatever is typed is submitted with the form and
    matched server-side against the store's DFID / business name / name.
    The suggestion list is only a helper — picking from it is optional.
--}}
@php
    $storeOptions = ($stores ?? \App\Models\Store::forPicker())->map(fn ($s) => [
        'label' => ($s->dfid ? $s->dfid.' · ' : '').($s->business_name ?? $s->name),
        'hay'   => mb_strtolower(trim($s->dfid.' '.$s->business_name.' '.$s->name)),
    ])->values();
@endphp
<div class="store-combo" data-store-search data-stores="{{ $storeOptions->toJson() }}">
    <input {{ $attributes->merge(['class' => 'input', 'style' => "width:$width"]) }} name="{{ $name }}" type="search" autocomplete="off"
           role="combobox" aria-expanded="false" aria-label="Store"
           value="{{ request($name) }}" placeholder="{{ $placeholder }}">
    <ul class="store-combo-list" role="listbox" hidden></ul>
</div>

@once
@push('scripts')
<script>
// Store search box: suggestions matching store ID (dfid) and name; Enter submits what was typed.
document.querySelectorAll('[data-store-search]').forEach(function (root) {
    var stores = JSON.parse(root.dataset.stores || '[]');
    var input  = root.querySelector('input');
    var list   = root.querySelector('.store-combo-list');
    var shown  = [];
    var active = -1;

    function filter(q) {
        var tokens = q.toLowerCase().replace(/·/g, ' ').split(/\s+/).filter(Boolean);
        if (!tokens.length) return stores;
        return stores.filter(function (s) {
            return tokens.every(function (t) { return s.hay.indexOf(t) !== -1; });
        });
    }

    function render(q) {
        shown = filter(q);
        active = -1;
        list.innerHTML = '';
        if (!shown.length) {
            var li = document.createElement('li');
            li.className = 'empty';
            li.textContent = 'No store matches "' + q + '"';
            list.appendChild(li);
        }
        shown.forEach(function (s) {
            var li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.textContent = s.label;
            li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(s); });
            list.appendChild(li);
        });
        open();
    }

    function highlight(i) {
        var items = list.querySelectorAll('li[role=option]');
        if (!items.length) return;
        active = (i + items.length) % items.length;
        items.forEach(function (li, k) { li.classList.toggle('active', k === active); });
        items[active].scrollIntoView({ block: 'nearest' });
    }

    function open()  { list.hidden = false; input.setAttribute('aria-expanded', 'true'); }
    function close() { list.hidden = true;  input.setAttribute('aria-expanded', 'false'); }

    function choose(s) {
        input.value = s.label;
        close();
    }

    input.addEventListener('focus', function () { render(input.value); });
    input.addEventListener('input', function () { render(input.value); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); list.hidden ? render(input.value) : highlight(active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
        // Enter submits whatever was typed, unless a suggestion was picked with the arrow keys.
        else if (e.key === 'Enter' && !list.hidden && active >= 0 && shown[active]) { e.preventDefault(); choose(shown[active]); }
        else if (e.key === 'Escape') close();
    });
    input.addEventListener('blur', close);
});
</script>
@endpush
@endonce
````

### A2. `resources/views/components/order-items.blade.php`

Notun file. Ei path e banan, niche er puro code boshan.

````blade
@props([
    'order',                 // OrderMirror with `items` eager-loaded (OrderMirror::ITEM_COLUMNS)
    'limit' => 3,            // lines shown before "+N more"
    'href'  => null,         // "+N more" target; defaults to the order page
])
{{-- Products × quantity for one order, shown inline in order lists. Styles: .oi-* in public/css/oms.css --}}
@php $items = $order?->items ?? collect(); @endphp
<div class="oi-cell">
    @if($items->isEmpty())
        <span style="color:var(--a-text-3)">—</span>
    @else
        @php $lines = $items->count(); $pcs = $items->sum('qty'); @endphp
        <div class="oi-summary">{{ $lines }} {{ Str::plural('item', $lines) }} · {{ $pcs }} {{ Str::plural('pc', $pcs) }}</div>
        <ul class="oi-list">
            @foreach($items->take($limit) as $it)
                <li title="{{ $it->name }}{{ $it->variant_label ? ' · '.$it->variant_label : '' }} × {{ $it->qty }}">
                    <span class="oi-qty">{{ $it->qty }}×</span>
                    <span class="oi-name">{{ $it->name }}@if($it->variant_label)<small>{{ $it->variant_label }}</small>@endif</span>
                </li>
            @endforeach
        </ul>
        @if($lines > $limit)
            <a href="{{ $href ?? route('orders.show', $order) }}" class="oi-more">+{{ $lines - $limit }} more</a>
        @endif
    @endif
</div>
````

### A3. `resources/views/components/order-pick.blade.php`

Notun file. Ei path e banan, niche er puro code boshan.

````blade
@props([
    'order',                 // OrderMirror with `items` eager-loaded (OrderMirror::ITEM_COLUMNS)
])
{{-- Row checkbox for order lists. Carries the order's products ([name, variant, qty])
     so <x-order-pick-bar> (in the layout) can total and list whatever is ticked. --}}
@php $items = $order->items ?? collect(); @endphp
<input type="checkbox" value="{{ $order->id }}" data-order-pick
       data-lines="{{ $items->count() }}" data-pcs="{{ $items->sum('qty') }}"
       data-items="{{ $items->map(fn ($i) => [$i->name, $i->variant_label, (int) $i->qty])->values()->toJson() }}"
       aria-label="Select order {{ $order->order_number }}"
       {{ $attributes->merge(['style' => 'accent-color:var(--a-accent)']) }}>
````

### A4. `resources/views/components/order-pick-all.blade.php`

Notun file. Ei path e banan, niche er puro code boshan.

````blade
{{-- Header checkbox: ticks every <x-order-pick> in the same table (handled by <x-order-pick-bar>). --}}
<input type="checkbox" data-order-pick-all aria-label="Select all orders in this table"
       {{ $attributes->merge(['style' => 'accent-color:var(--a-accent)']) }}>
````

### A5. `resources/views/components/order-pick-bar.blade.php`

Notun file. Ei path e banan, niche er puro code boshan.

````blade
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
````

## Section B — Edit kora file (diff: `-` muchun, `+` add korun)

### B1. `app/Models/OrderMirror.php`

````diff
@@ -70,6 +70,21 @@ public function placedBy()        { return $this->belongsTo(User::class, 'placed
     public function exchanges()       { return $this->hasMany(Exchange::class, 'original_order_id'); }
     public function exchangeOriginal(){ return $this->belongsTo(self::class, 'exchange_of_order_id'); }
 
+    /** Columns the order-items cell (<x-order-items>) needs — eager-load as "items:".self::ITEM_COLUMNS. */
+    public const ITEM_COLUMNS = 'id,order_id,name,variant_label,qty';
+
+    /**
+     * Store filter shared by every order list: an exact store id (?store_id=,
+     * kept for old links) and/or the free-text store search (?store=), where
+     * every word must match the same store's DFID or name.
+     */
+    public function scopeStoreFilter($q, $storeId = null, ?string $storeSearch = null)
+    {
+        return $q
+            ->when($storeId, fn ($q, $id) => $q->where('store_id', $id))
+            ->when(Store::searchWords($storeSearch), fn ($q) => $q->whereHas('store', fn ($s) => $s->matching($storeSearch)));
+    }
+
     public function statusLabel(): string
     {
         return self::LABELS[$this->status] ?? ucwords(str_replace('_',' ',(string)$this->status));
````

### B2. `app/Models/Store.php`

````diff
@@ -53,6 +53,32 @@ protected function courierCredentials(): Attribute
         );
     }
 
+    /**
+     * Split a free-text store search into words. Spaces and the "·" used in
+     * store labels ("DFSNK001 · Sneaker Lab BD") both separate words.
+     */
+    public static function searchWords(?string $term): array
+    {
+        return preg_split('/[\s·]+/u', trim((string) $term), -1, PREG_SPLIT_NO_EMPTY);
+    }
+
+    /** Stores whose DFID / business name / name contain every word of $term. */
+    public function scopeMatching($q, ?string $term)
+    {
+        foreach (static::searchWords($term) as $w) {
+            $q->where(fn ($x) => $x->where('dfid', 'like', "%$w%")
+                ->orWhere('business_name', 'like', "%$w%")
+                ->orWhere('name', 'like', "%$w%"));
+        }
+        return $q;
+    }
+
+    /** The store list used by store filters / search suggestions. */
+    public static function forPicker()
+    {
+        return static::orderBy('business_name')->get(['id', 'dfid', 'business_name', 'name']);
+    }
+
     /** Return stored credentials for a specific courier slug. */
     public function credentialsFor(string $slug): array
     {
````

### B3. `app/Http/Controllers/AllOrdersController.php`

````diff
@@ -75,7 +75,7 @@ private function buildData(Request $r): array
 
         // Pagination links always point to /orders, regardless of whether this
         // request came from the main page or the AJAX /orders/rows endpoint.
-        $orders = $query->with('store:id,dfid,business_name,name', 'consignments')
+        $orders = $query->with('store:id,dfid,business_name,name', 'consignments', 'items:'.OrderMirror::ITEM_COLUMNS)
             ->paginate($perPage)
             ->withPath(route('orders.index'))
             ->appends($r->except('page'));
@@ -87,7 +87,7 @@ private function buildData(Request $r): array
             ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
         $totalCount = (clone $countQuery)->count();
 
-        $stores   = Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
+        $stores   = Store::forPicker();
         $couriers = array_keys(CourierManager::ADAPTERS);
         $statuses = S::STATUSES;
         $labels   = S::LABELS;
@@ -116,7 +116,7 @@ private function filtered(Request $r, bool $ignoreStatus = false)
                 $statuses = is_array($r->status) ? $r->status : explode(',', $r->status);
                 $q->whereIn('status', array_filter($statuses));
             })
-            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
+            ->storeFilter($r->store_id, $r->store)
             ->when($r->payment_method, fn($q,$p) => $q->where('payment_method', $p))
             ->when($r->zone, fn($q,$z) => $q->where('shipping_zone', $z))
             ->when($r->courier, fn($q,$slug) => $q->whereHas('consignments', fn($c) => $c->where('courier_slug', $slug)))
````

### B4. `app/Http/Controllers/CustomerController.php`

````diff
@@ -41,7 +41,7 @@ public function index(Request $r)
     public function show(CustomerMirror $customer)
     {
         $customer->load('store:id,dfid,business_name,name');
-        $orders = OrderMirror::with('store:id,dfid,business_name,name')
+        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS)
             ->where('store_id', $customer->store_id)
             ->where(function ($q) use ($customer) {
                 $q->where('customer_email', $customer->email)
````

### B5. `app/Http/Controllers/DashboardController.php`

````diff
@@ -20,7 +20,7 @@ public function __invoke(Request $request)
             ['label' => 'Returns',      'value' => OrderMirror::whereIn('status', [S::RETURN_PENDING, S::RETURNED, S::AWAITING_RETURN_PRODUCT])->count(), 'sub' => 'Reverse logistics', 'href' => route('returns.index'), 'tone' => 'danger'],
         ];
 
-        $recent = OrderMirror::with('store:id,dfid,business_name,name')->latest('placed_at')->limit(10)->get();
+        $recent = OrderMirror::with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS)->latest('placed_at')->limit(10)->get();
         $low    = ProductMirror::with('store:id,dfid,business_name,name')
             ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
             ->where('manage_stock', true)->limit(10)->get();
````

### B6. `app/Http/Controllers/DispatchController.php`

````diff
@@ -13,8 +13,9 @@ class DispatchController extends Controller
     public function index(Request $r)
     {
         // Packed parcels waiting to be handed over.
-        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'consignments')
+        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'consignments', 'items:'.OrderMirror::ITEM_COLUMNS)
             ->where('status', OrderStateMachine::PACKED)
+            ->storeFilter($r->store_id, $r->store)
             ->latest('packed_at')
             ->get()
             ->groupBy(fn ($o) => optional($o->consignments->last())->courier_slug ?? 'manual');
@@ -30,8 +31,9 @@ public function index(Request $r)
 
     public function rows(Request $r)
     {
-        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'consignments')
+        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'consignments', 'items:'.OrderMirror::ITEM_COLUMNS)
             ->where('status', OrderStateMachine::PACKED)
+            ->storeFilter($r->store_id, $r->store)
             ->latest('packed_at')
             ->get()
             ->groupBy(fn ($o) => optional($o->consignments->last())->courier_slug ?? 'manual');
@@ -75,6 +77,7 @@ public function exportCsv(Request $r): StreamedResponse
         $courier = $r->query('courier');
         $rows = OrderMirror::with('store','consignments')
             ->where('status', OrderStateMachine::PACKED)
+            ->storeFilter($r->store_id, $r->store)
             ->when($courier, fn($q,$c) => $q->whereHas('consignments', fn($x) => $x->where('courier_slug', $c)))
             ->get();
         return response()->streamDownload(function () use ($rows) {
````

### B7. `app/Http/Controllers/InboxController.php`

````diff
@@ -11,8 +11,8 @@ class InboxController extends Controller
     public function index(Request $r)
     {
         $q = OrderMirror::query()
-            ->with('store:id,dfid,business_name,name')
-            ->when($r->store_id, fn($q, $id) => $q->where('store_id', $id))
+            ->with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS)
+            ->storeFilter($r->store_id, $r->store)
             ->when($r->status, fn($q,$s) => $q->where('status', $s), fn($q) => $q->whereIn('status', ['pending','confirmed']))
             ->when($r->q, function ($q,$term) {
                 $q->where(function ($x) use ($term) {
@@ -25,7 +25,7 @@ public function index(Request $r)
             ->latest('placed_at');
 
         $orders = $q->paginate(25)->withQueryString();
-        $stores = Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
+        $stores = Store::forPicker();
         return view('inbox.index', compact('orders','stores'));
     }
 }
````

### B8. `app/Http/Controllers/OrderCreationController.php`

````diff
@@ -25,9 +25,10 @@ class OrderCreationController extends Controller
     public function index(Request $r)
     {
         $u = $r->user();
-        $q = OrderMirror::with('store:id,dfid,business_name,name','placedBy:id,name')
+        $q = OrderMirror::with('store:id,dfid,business_name,name','placedBy:id,name','items:'.OrderMirror::ITEM_COLUMNS)
             ->whereNotNull('placed_by_user_id')
             ->when(! $u->isAdmin(), fn($q) => $q->where('placed_by_user_id', $u->id))
+            ->storeFilter($r->store_id, $r->store)
             ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                 $x->where('order_number','like',"%$t%")
                   ->orWhere('customer_name','like',"%$t%")
````

### B9. `app/Http/Controllers/PackingController.php`

````diff
@@ -17,10 +17,10 @@ class PackingController extends Controller
 {
     public function index(Request $r)
     {
-        $orders = OrderMirror::with('store:id,dfid,business_name,name','consignments','packer:id,name')
+        $orders = OrderMirror::with('store:id,dfid,business_name,name','consignments','packer:id,name','items:'.OrderMirror::ITEM_COLUMNS)
             ->where('status', OrderStateMachine::PROCESSING)
             ->whereHas('consignments')
-            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
+            ->storeFilter($r->store_id, $r->store)
             ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                 $x->where('order_number','like',"%$t%")
                   ->orWhere('customer_name','like',"%$t%")
@@ -28,16 +28,16 @@ public function index(Request $r)
                   ->orWhereHas('consignments', fn($c) => $c->where('tracking_code','like',"%$t%")->orWhere('consignment_id','like',"%$t%"));
             }))
             ->latest('processing_at')->paginate(30);
-        $stores = \App\Models\Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
+        $stores = \App\Models\Store::forPicker();
         return view('packing.index', compact('orders','stores'));
     }
 
     public function rows(Request $r)
     {
-        $orders = OrderMirror::with('store:id,dfid,business_name,name','consignments','packer:id,name')
+        $orders = OrderMirror::with('store:id,dfid,business_name,name','consignments','packer:id,name','items:'.OrderMirror::ITEM_COLUMNS)
             ->where('status', OrderStateMachine::PROCESSING)
             ->whereHas('consignments')
-            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
+            ->storeFilter($r->store_id, $r->store)
             ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                 $x->where('order_number','like',"%$t%")
                   ->orWhere('customer_name','like',"%$t%")
````

### B10. `app/Http/Controllers/ProcessingController.php`

````diff
@@ -14,26 +14,26 @@ public function index(Request $r)
     {
         // Processing = confirmed orders NOT yet sent to the courier (no consignment).
         // Once the courier is booked the order moves to the Packing queue.
-        $orders = OrderMirror::with('store:id,dfid,business_name,name')
+        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS)
             ->where('status', OrderStateMachine::PROCESSING)
             ->whereDoesntHave('consignments')
-            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
+            ->storeFilter($r->store_id, $r->store)
             ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                 $x->where('order_number','like',"%$t%")
                   ->orWhere('customer_name','like',"%$t%")
                   ->orWhere('customer_phone','like',"%$t%");
             }))
             ->latest('processing_at')->paginate(30);
-        $stores = \App\Models\Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
+        $stores = \App\Models\Store::forPicker();
         return view('processing.index', compact('orders','stores'));
     }
 
     public function rows(Request $r)
     {
-        $orders = OrderMirror::with('store:id,dfid,business_name,name')
+        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS)
             ->where('status', OrderStateMachine::PROCESSING)
             ->whereDoesntHave('consignments')
-            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
+            ->storeFilter($r->store_id, $r->store)
             ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                 $x->where('order_number','like',"%$t%")
                   ->orWhere('customer_name','like',"%$t%")
````

### B11. `app/Http/Controllers/RefundController.php`

````diff
@@ -10,15 +10,16 @@ class RefundController extends Controller
 {
     public function index(Request $r)
     {
-        $refunds = OrderMirror::with('store:id,dfid,business_name,name')
+        $refunds = OrderMirror::with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS)
             ->whereNotNull('refund_status')
             ->when($r->status, fn($q, $s) => $q->where('refund_status', $s))
-            ->when($r->store,  fn($q, $s) => $q->where('store_id', $s))
+            ->storeFilter($r->store_id, $r->store)
             ->latest('refunded_at')
             ->paginate(30)
             ->withQueryString();
 
         $counts = OrderMirror::whereNotNull('refund_status')
+            ->storeFilter($r->store_id, $r->store)
             ->selectRaw('refund_status, COUNT(*) as c')
             ->groupBy('refund_status')
             ->pluck('c', 'refund_status');
````

### B12. `app/Http/Controllers/ReturnsController.php`

````diff
@@ -20,13 +20,14 @@ public function index(Request $r)
             OrderStateMachine::AWAITING_RETURN_PRODUCT,
         ];
 
-        $orders = OrderMirror::with('store:id,dfid,business_name,name')
+        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS)
             ->whereIn('status', $states)
+            ->storeFilter($r->store_id, $r->store)
             ->when($r->status, fn($q,$s) => $q->where('status', $s))
             ->latest('return_pending_at')->paginate(30)->withQueryString();
 
         // Counts per state for the filter chips.
-        $counts = OrderMirror::query()->whereIn('status', $states)
+        $counts = OrderMirror::query()->whereIn('status', $states)->storeFilter($r->store_id, $r->store)
             ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
 
         return view('returns.index', compact('orders', 'counts'));
@@ -39,14 +40,15 @@ public function rows(Request $r)
             OrderStateMachine::RETURNED,
             OrderStateMachine::AWAITING_RETURN_PRODUCT,
         ];
-        $orders = OrderMirror::with('store:id,dfid,business_name,name')
+        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS)
             ->whereIn('status', $states)
+            ->storeFilter($r->store_id, $r->store)
             ->when($r->status, fn($q,$s) => $q->where('status', $s))
             ->latest('return_pending_at')
             ->paginate(30)
             ->withPath(route('returns.index'))
             ->appends($r->except('page'));
-        $counts = OrderMirror::query()->whereIn('status', $states)
+        $counts = OrderMirror::query()->whereIn('status', $states)->storeFilter($r->store_id, $r->store)
             ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
         return response()->json([
             'html' => view('returns._region', compact('orders', 'counts'))->render(),
````

### B13. `app/Http/Controllers/VerificationController.php`

````diff
@@ -12,29 +12,29 @@ class VerificationController extends Controller
 {
     public function index(Request $r)
     {
-        $orders = OrderMirror::with('store:id,dfid,business_name,name')
+        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS)
             ->where('status', OrderStateMachine::PENDING_VERIFICATION)
             ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                 $x->where('order_number','like',"%$t%")
                   ->orWhere('customer_name','like',"%$t%")
                   ->orWhere('customer_phone','like',"%$t%");
             }))
-            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
+            ->storeFilter($r->store_id, $r->store)
             ->latest('placed_at')->paginate(25);
-        $stores = \App\Models\Store::orderBy('business_name')->get(['id','dfid','business_name','name']);
+        $stores = \App\Models\Store::forPicker();
         return view('verification.index', compact('orders','stores'));
     }
 
     public function rows(Request $r)
     {
-        $orders = OrderMirror::with('store:id,dfid,business_name,name')
+        $orders = OrderMirror::with('store:id,dfid,business_name,name', 'items:'.OrderMirror::ITEM_COLUMNS)
             ->where('status', OrderStateMachine::PENDING_VERIFICATION)
             ->when($r->q, fn($q,$t) => $q->where(function($x) use ($t) {
                 $x->where('order_number','like',"%$t%")
                   ->orWhere('customer_name','like',"%$t%")
                   ->orWhere('customer_phone','like',"%$t%");
             }))
-            ->when($r->store_id, fn($q,$id) => $q->where('store_id', $id))
+            ->storeFilter($r->store_id, $r->store)
             ->latest('placed_at')
             ->paginate(25)
             ->withPath(route('verification.index'))
````

### B14. `public/css/oms.css`

````diff
@@ -294,3 +294,69 @@ .input:focus-visible{
 
 /* ---------- nicer empty card padding ---------- */
 .admin-card .admin-card-body + .admin-card-body{border-top:1px solid var(--a-border)}
+
+/* ---------- <x-order-items>: products × qty inside order lists ---------- */
+.oi-cell{width:250px;min-width:200px;max-width:260px}
+.oi-summary{font-size:10.5px;font-weight:600;letter-spacing:.02em;color:var(--a-text-2);margin-bottom:4px;text-transform:uppercase}
+.oi-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:4px}
+.oi-list li{display:flex;align-items:flex-start;gap:7px;min-width:0}
+.oi-qty{flex:0 0 auto;min-width:28px;padding:1px 6px;border-radius:999px;text-align:center;font-size:11px;font-weight:700;line-height:16px;background:var(--a-accent-soft);color:var(--a-accent);border:1px solid var(--a-accent-border);font-variant-numeric:tabular-nums}
+.oi-name{min-width:0;font-size:12px;line-height:18px;color:var(--a-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
+.oi-name small{display:block;font-size:10.5px;line-height:14px;color:var(--a-text-3);overflow:hidden;text-overflow:ellipsis}
+.oi-more{display:inline-block;margin-top:4px;font-size:11px;font-weight:600;color:var(--a-accent);text-decoration:none}
+.oi-more:hover{text-decoration:underline}
+
+/* ---------- <x-store-search>: free-text store filter with suggestions ---------- */
+.store-combo{position:relative;display:inline-block}
+.store-combo-list{position:absolute;z-index:30;top:calc(100% + 4px);left:0;min-width:100%;max-height:260px;overflow-y:auto;margin:0;padding:4px;list-style:none;background:var(--a-surface);border:1px solid var(--a-border);border-radius:var(--a-r);box-shadow:0 8px 24px rgba(0,0,0,.18)}
+.store-combo-list li{padding:7px 10px;border-radius:6px;cursor:pointer;font-size:13px;color:var(--a-text);white-space:nowrap}
+.store-combo-list li.active,.store-combo-list li:hover{background:var(--a-accent-soft)}
+.store-combo-list li.empty{cursor:default;color:var(--a-text-3);background:none}
+
+/* ---------- <x-order-pick>: ticked rows ---------- */
+.atable tr:has(> td > input[data-order-pick]:checked) > td{background:var(--a-accent-soft)}
+.atable tr:has(> td > input[data-order-pick]:checked) > td:first-child{box-shadow:inset 3px 0 0 var(--a-accent)}
+input[data-order-pick],input[data-order-pick-all]{width:16px;height:16px;cursor:pointer;vertical-align:middle}
+
+/* ---------- <x-order-pick-bar>: totals + product list for the ticked orders ---------- */
+.opick{position:fixed;z-index:250;bottom:20px;left:calc(50% + var(--a-sidebar-w) / 2);transform:translateX(-50%);width:max-content;max-width:calc(100vw - 32px);display:flex;flex-direction:column;align-items:stretch;gap:8px;animation:opick-in .22s cubic-bezier(.2,.8,.3,1)}
+.opick[hidden]{display:none}
+.opick-bar{display:flex;align-items:center;gap:6px;padding:8px 8px 8px 10px;border-radius:16px;background:var(--a-surface);color:var(--a-text);border:1px solid var(--a-accent-border);box-shadow:0 14px 40px rgba(0,0,0,.22),0 2px 6px rgba(0,0,0,.08)}
+.opick-badge{flex:0 0 auto;display:grid;place-items:center;width:34px;height:34px;margin-right:4px;border-radius:10px;background:var(--a-accent);color:#fff}
+.opick-stat{display:flex;flex-direction:column;align-items:flex-start;min-width:64px;padding:0 14px;line-height:1.1;border-left:1px solid var(--a-border)}
+.opick-badge + .opick-stat{border-left:0;padding-left:4px}
+.opick-stat strong{font-size:19px;font-weight:800;font-variant-numeric:tabular-nums;color:var(--a-text)}
+.opick-stat strong.bump{animation:opick-bump .28s ease}
+.opick-stat span{margin-top:2px;font-size:10.5px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--a-text-2)}
+.opick-actions{display:flex;align-items:center;gap:6px;margin-left:8px;padding-left:12px;border-left:1px solid var(--a-border)}
+.opick-btn{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;border-radius:10px;border:1px solid var(--a-accent-border);background:var(--a-accent-soft);color:var(--a-accent);font:inherit;font-size:12.5px;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .15s}
+.opick-btn:hover{background:color-mix(in srgb,var(--a-accent) 16%,transparent)}
+.opick-btn svg{transition:transform .2s}
+.opick.is-open [data-opick-toggle] svg{transform:rotate(180deg)}
+.opick-btn-icon{width:34px;padding:0;justify-content:center;border-color:var(--a-border);background:transparent;color:var(--a-text-2)}
+.opick-btn-icon:hover{background:var(--a-surface-2);color:var(--a-text)}
+.opick-btn:focus-visible{outline:2px solid var(--a-accent);outline-offset:2px}
+.opick-panel{border-radius:16px;background:var(--a-surface);color:var(--a-text);border:1px solid var(--a-border);box-shadow:0 14px 40px rgba(0,0,0,.22);overflow:hidden;animation:opick-in .18s ease}
+.opick-panel[hidden]{display:none}
+.opick-panel-head{display:flex;justify-content:space-between;align-items:baseline;gap:12px;padding:12px 16px;border-bottom:1px solid var(--a-border);font-size:13px}
+.opick-panel-head span{font-size:11.5px;color:var(--a-text-2)}
+.opick-plist{list-style:none;margin:0;padding:6px 8px;max-height:min(320px,50vh);overflow-y:auto}
+.opick-plist li{display:flex;align-items:center;gap:10px;padding:7px 8px;border-radius:8px}
+.opick-plist li:nth-child(even){background:var(--a-surface-2)}
+.opick-pname{flex:1;min-width:0;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
+.opick-pname small{margin-left:6px;font-size:11px;color:var(--a-text-3)}
+.opick-porders{flex:0 0 auto;font-size:11px;color:var(--a-text-3)}
+@keyframes opick-in{from{opacity:0;translate:0 12px}to{opacity:1;translate:0 0}}
+@keyframes opick-bump{40%{transform:scale(1.18);color:var(--a-accent)}}
+@media(prefers-reduced-motion:reduce){.opick,.opick-panel,.opick-stat strong.bump{animation:none}}
+@media(max-width:900px){
+    .opick{left:50%;bottom:12px;width:calc(100vw - 32px)}
+    .opick-bar{gap:2px;padding:6px}
+    .opick-badge{display:none}
+    .opick-stat{flex:1;min-width:0;padding:0 8px}
+    .opick-stat:first-of-type{border-left:0}
+    .opick-stat strong{font-size:16px}
+    .opick-stat span{font-size:9.5px}
+    .opick-actions{margin-left:2px;padding-left:6px}
+    .opick-btn{padding:0 10px}
+}
````

### B15. `resources/views/layouts/app.blade.php`

````diff
@@ -221,6 +221,7 @@
             }
         });
     </script>
+    <x-order-pick-bar/>
     @stack('scripts')
 </body>
 </html>
````

### B16. `resources/views/dashboard.blade.php`

````diff
@@ -15,18 +15,20 @@
             <div class="admin-card-head"><x-admin.section-head icon="cart" title="Recent orders" description="Most recent orders from any merchant."/></div>
             <div class="atable-wrap">
                 <table class="atable">
-                    <thead><tr><th>Order</th><th>Store</th><th>Customer</th><th>Status</th><th style="text-align:right">Total</th></tr></thead>
+                    <thead><tr><th style="width:36px"><x-order-pick-all/></th><th>Order</th><th>Store</th><th>Customer</th><th>Items</th><th>Status</th><th style="text-align:right">Total</th></tr></thead>
                     <tbody>
                     @forelse($recentOrders as $o)
                         <tr>
+                            <td><x-order-pick :order="$o"/></td>
                             <td><a href="{{ route('orders.show', $o) }}" style="font-weight:700;color:var(--a-accent);text-decoration:none">{{ $o->order_number }}</a></td>
                             <td style="font-size:12px"><strong>{{ optional($o->store)->dfid ?? '—' }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name ?? '' }}</div></td>
                             <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div></td>
+                            <td><x-order-items :order="$o"/></td>
                             <td><x-admin.pill :status="$o->status" :label="$o->statusLabel()"/></td>
                             <td style="text-align:right;font-weight:600">৳{{ number_format($o->grand_total, 2) }}</td>
                         </tr>
                     @empty
-                        <tr><td colspan="5"><x-admin.empty icon="inbox" title="No orders yet" description="Register a store and run sync to pull orders from DFCOMMERCE." /></td></tr>
+                        <tr><td colspan="7"><x-admin.empty icon="inbox" title="No orders yet" description="Register a store and run sync to pull orders from DFCOMMERCE." /></td></tr>
                     @endforelse
                     </tbody>
                 </table>
````

### B17. `resources/views/customers/show.blade.php`

````diff
@@ -9,12 +9,14 @@
         @else
             <div class="atable-wrap">
                 <table class="atable">
-                    <thead><tr><th>Order</th><th>Placed</th><th>Status</th><th style="text-align:right">Total</th></tr></thead>
+                    <thead><tr><th style="width:36px"><x-order-pick-all/></th><th>Order</th><th>Placed</th><th>Items</th><th>Status</th><th style="text-align:right">Total</th></tr></thead>
                     <tbody>
                     @foreach($orders as $o)
                         <tr>
+                            <td><x-order-pick :order="$o"/></td>
                             <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:600;text-decoration:none">{{ $o->order_number }}</a></td>
                             <td>{{ optional($o->placed_at)->diffForHumans() }}</td>
+                            <td><x-order-items :order="$o"/></td>
                             <td><x-admin.pill :status="$o->status"/></td>
                             <td style="text-align:right;font-weight:600">৳{{ number_format($o->grand_total, 2) }}</td>
                         </tr>
````

### B18. `resources/views/orders/all.blade.php`

````diff
@@ -36,12 +36,9 @@
                 <span style="font-size:11px;color:var(--a-text-2)">Search</span>
                 <input class="input" name="q" value="{{ request('q') }}" placeholder="Order # · name · phone · email · tracking · consignment · SKU">
             </label>
-            <label><span style="font-size:11px;color:var(--a-text-2)">Store</span>
-                <select class="input" name="store_id" style="width:auto">
-                    <option value="">All</option>
-                    @foreach($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id')==$s->id)>{{ ($s->dfid?$s->dfid.' · ':'').($s->business_name ?? $s->name) }}</option>@endforeach
-                </select>
-            </label>
+            <div><span style="display:block;font-size:11px;color:var(--a-text-2)">Store</span>
+                <x-store-search :stores="$stores" width="260px"/>
+            </div>
             <label><span style="font-size:11px;color:var(--a-text-2)">Payment</span>
                 <select class="input" name="payment_method" style="width:auto">
                     <option value="">Any</option>
@@ -72,7 +69,7 @@
             {{-- keep current status filter when applying other filters --}}
             @foreach($activeStatuses as $st)<input type="hidden" name="status[]" value="{{ $st }}">@endforeach
             <button class="btn btn-dark">Apply</button>
-            @if(request()->hasAny(['q','store_id','payment_method','courier','zone','source','from','to','status']))
+            @if(request()->hasAny(['q','store','store_id','payment_method','courier','zone','source','from','to','status']))
                 <a href="{{ route('orders.index') }}" class="btn btn-ghost">Clear</a>
             @endif
         </div>
@@ -162,7 +159,10 @@ function showError() {
                     if (!rowsResp.ok) throw new Error('rows ' + rowsResp.status);
 
                     var html = await rowsResp.text();
+                    // Keep ticked orders ticked across the refresh.
+                    var picked = new Set([...region.querySelectorAll('input[data-order-pick]:checked')].map(function (c) { return c.value; }));
                     region.innerHTML = html;
+                    region.querySelectorAll('input[data-order-pick]').forEach(function (c) { c.checked = picked.has(c.value); });
                 }
 
                 lastSig = sig;
````

### B19. `resources/views/orders/_table.blade.php`

````diff
@@ -50,6 +50,7 @@ class="pill pill-{{ $st }} {{ $activeStatuses->contains($st) ? 'pill-active' : '
         <div class="atable-wrap">
             <table class="atable">
                 <thead><tr>
+                    <th style="width:36px"><x-order-pick-all/></th>
                     {!! $th('order','Order') !!}
                     <th>Store</th>
                     {!! $th('customer','Customer') !!}
@@ -57,12 +58,14 @@ class="pill pill-{{ $st }} {{ $activeStatuses->contains($st) ? 'pill-active' : '
                     {!! $th('placed','Placed') !!}
                     {!! $th('total','Total','right') !!}
                     {!! $th('status','Status') !!}
+                    <th>Items</th>
                     <th></th>
                 </tr></thead>
                 <tbody>
                     @foreach($orders as $o)
                         @php $c = $o->consignments->last(); @endphp
                         <tr>
+                            <td><x-order-pick :order="$o"/></td>
                             <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a>
                                 @if($o->placed_by_user_id)<div style="font-size:10px;color:var(--a-text-3)">OMS-placed</div>@endif</td>
                             <td style="font-size:12px"><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
@@ -73,6 +76,7 @@ class="pill pill-{{ $st }} {{ $activeStatuses->contains($st) ? 'pill-active' : '
                             <td style="white-space:nowrap;font-size:12px">{{ optional($o->placed_at)->format('d M Y') ?? '—' }}<div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->placed_at)->format('H:i') }}</div></td>
                             <td style="text-align:right;font-weight:600">৳{{ number_format($o->grand_total) }}</td>
                             <td><x-admin.pill :status="$o->status" :label="$o->statusLabel()"/></td>
+                            <td><x-order-items :order="$o"/></td>
                             <td style="text-align:right"><a href="{{ route('orders.show', $o) }}" class="btn btn-outline btn-sm">Open</a></td>
                         </tr>
                     @endforeach
````

### B20. `resources/views/orders-new/index.blade.php`

````diff
@@ -6,9 +6,13 @@
     </div>
 
     <form method="GET" class="admin-card" style="margin-bottom:14px">
-        <div class="admin-card-body" style="display:flex;gap:10px">
+        <div class="admin-card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
             <input class="input" name="q" value="{{ request('q') }}" placeholder="Order # · name · phone" style="width:280px">
+            <x-store-search/>
             <button class="btn btn-dark">Apply</button>
+            @if(request()->hasAny(['q','store','store_id']))
+                <a href="{{ route('orders-new.index') }}" class="btn btn-ghost">Clear</a>
+            @endif
         </div>
     </form>
 
@@ -19,13 +23,15 @@
         @else
             <div class="atable-wrap">
                 <table class="atable">
-                    <thead><tr><th>Order</th><th>Store</th><th>Customer</th><th>Placed</th><th>Status</th><th style="text-align:right">Total</th><th></th></tr></thead>
+                    <thead><tr><th style="width:36px"><x-order-pick-all/></th><th>Order</th><th>Store</th><th>Customer</th><th>Items</th><th>Placed</th><th>Status</th><th style="text-align:right">Total</th><th></th></tr></thead>
                     <tbody>
                     @foreach($orders as $o)
                         <tr>
+                            <td><x-order-pick :order="$o"/></td>
                             <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a></td>
                             <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
                             <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div></td>
+                            <td><x-order-items :order="$o"/></td>
                             <td>{{ optional($o->placed_at)->diffForHumans() }}</td>
                             <td><x-admin.pill :status="$o->status" :label="$o->statusLabel()"/></td>
                             <td style="text-align:right;font-weight:600">৳{{ number_format($o->grand_total) }}</td>
````

### B21. `resources/views/inbox/index.blade.php`

````diff
@@ -10,12 +10,7 @@
             <x-admin.section-head icon="inbox" title="Incoming orders" description="Click a row to open. Use Accept & Pick to spawn a picking session."/>
             <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                 <input type="search" name="q" value="{{ request('q') }}" placeholder="Order # · name · phone" class="input" style="width:240px;padding:7px 12px;font-size:13px">
-                <select name="store_id" class="input" style="width:auto;padding:7px 10px;font-size:13px" onchange="this.form.submit()">
-                    <option value="">All stores</option>
-                    @foreach($stores as $s)
-                        <option value="{{ $s->id }}" @selected(request('store_id')==$s->id)>{{ ($s->dfid ? $s->dfid.' · ' : '').($s->business_name ?? $s->name) }}</option>
-                    @endforeach
-                </select>
+                <x-store-search :stores="$stores" style="padding:7px 12px;font-size:13px"/>
                 <select name="status" class="input" style="width:auto;padding:7px 10px;font-size:13px" onchange="this.form.submit()">
                     <option value="">Pending / Confirmed</option>
                     @foreach(\App\Models\OrderMirror::STATUSES as $s)
@@ -23,7 +18,7 @@
                     @endforeach
                 </select>
                 <button class="btn btn-dark">Apply</button>
-                @if(request()->hasAny(['q','status','store_id','payment']))
+                @if(request()->hasAny(['q','status','store','store_id','payment']))
                     <a href="{{ route('inbox.index') }}" class="btn btn-ghost">Clear</a>
                 @endif
             </form>
@@ -36,9 +31,11 @@
                 <table class="atable">
                     <thead>
                         <tr>
+                            <th style="width:36px"><x-order-pick-all/></th>
                             <th>Order</th>
                             <th>Store</th>
                             <th>Customer</th>
+                            <th>Items</th>
                             <th>Placed</th>
                             <th style="text-align:right">Total</th>
                             <th>Status</th>
@@ -48,12 +45,14 @@
                     <tbody>
                     @foreach($orders as $o)
                         <tr>
+                            <td><x-order-pick :order="$o"/></td>
                             <td><a href="{{ route('orders.show', $o) }}" style="font-weight:700;color:var(--a-accent);text-decoration:none">{{ $o->order_number }}</a></td>
                             <td style="font-size:12px">
                                 <strong>{{ $o->store->dfid ?? '—' }}</strong>
                                 <div style="font-size:11px;color:var(--a-text-3)">{{ $o->store->business_name ?? $o->store->name ?? '' }}</div>
                             </td>
                             <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div></td>
+                            <td><x-order-items :order="$o"/></td>
                             <td style="white-space:nowrap">{{ optional($o->placed_at)->format('d M Y') ?? '—' }}<div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->placed_at)->format('H:i') }}</div></td>
                             <td style="text-align:right;font-weight:600">৳{{ number_format($o->grand_total) }}</td>
                             <td><x-admin.pill :status="$o->status" :label="$o->statusLabel()"/></td>
````

### B22. `resources/views/verification/index.blade.php`

````diff
@@ -9,10 +9,7 @@
             <x-admin.section-head icon="phone" title="Pending Verification" description="Call the customer · edit the order · confirm or cancel."/>
             <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                 <input type="search" name="q" value="{{ request('q') }}" placeholder="Order # · name · phone" class="input" style="width:240px">
-                <select name="store_id" class="input" onchange="this.form.submit()" style="width:auto">
-                    <option value="">All stores</option>
-                    @foreach($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id')==$s->id)>{{ ($s->dfid?$s->dfid.' · ':'').($s->business_name ?? $s->name) }}</option>@endforeach
-                </select>
+                <x-store-search :stores="$stores"/>
                 <button class="btn btn-dark">Apply</button>
             </form>
         </div>
@@ -37,8 +34,8 @@
                 <div class="atable-wrap">
                     <table class="atable">
                         <thead><tr>
-                            <th style="width:36px"><input type="checkbox" id="sel-all" style="accent-color:var(--a-accent)"></th>
-                            <th>Order</th><th>Store</th><th>Customer</th><th>Placed</th><th style="text-align:right">Total</th><th></th>
+                            <th style="width:36px"><x-order-pick-all id="sel-all"/></th>
+                            <th>Order</th><th>Store</th><th>Customer</th><th>Items</th><th>Placed</th><th style="text-align:right">Total</th><th></th>
                         </tr></thead>
                         <tbody id="verf-tbody">
                             @include('verification._rows')
````

### B23. `resources/views/verification/_rows.blade.php`

````diff
@@ -1,9 +1,10 @@
 @foreach($orders as $o)
 <tr>
-    <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" class="row-sel" style="accent-color:var(--a-accent)"></td>
+    <td><x-order-pick :order="$o" name="order_ids[]" class="row-sel"/></td>
     <td><a href="{{ route('verification.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a></td>
     <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
     <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div></td>
+    <td><x-order-items :order="$o" :href="route('verification.show', $o)"/></td>
     <td>{{ optional($o->placed_at)->diffForHumans() }}</td>
     <td style="text-align:right;font-weight:600">৳{{ number_format($o->grand_total) }}</td>
     <td style="text-align:right"><a href="{{ route('verification.show', $o) }}" class="btn btn-dark btn-sm">Open</a></td>
````

### B24. `resources/views/processing/index.blade.php`

````diff
@@ -7,10 +7,7 @@
     <form method="GET" class="admin-card" style="margin-bottom:14px">
         <div class="admin-card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
             <input class="input" name="q" value="{{ request('q') }}" placeholder="Order # · name · phone" style="width:280px">
-            <select class="input" name="store_id" onchange="this.form.submit()" style="width:auto">
-                <option value="">All stores</option>
-                @foreach($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id')==$s->id)>{{ ($s->dfid?$s->dfid.' · ':'').($s->business_name ?? $s->name) }}</option>@endforeach
-            </select>
+            <x-store-search :stores="$stores"/>
             <button class="btn btn-dark">Apply</button>
         </div>
     </form>
@@ -34,7 +31,7 @@
                     <table class="atable">
                         <thead>
                             <tr>
-                                <th style="width:36px"><input type="checkbox" id="sel-all" style="accent-color:var(--a-accent)"></th>
+                                <th style="width:36px"><x-order-pick-all id="sel-all"/></th>
                                 <th>Order</th><th>Store</th><th>Customer</th><th>Items</th><th>Aged</th><th></th>
                             </tr>
                         </thead>
````

### B25. `resources/views/processing/_rows.blade.php`

````diff
@@ -1,10 +1,10 @@
 @foreach($orders as $o)
 <tr>
-    <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" class="row-sel" style="accent-color:var(--a-accent)"></td>
+    <td><x-order-pick :order="$o" name="order_ids[]" class="row-sel"/></td>
     <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a></td>
     <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
     <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->shipping_city }}</div></td>
-    <td>{{ $o->items()->sum('qty') }}</td>
+    <td><x-order-items :order="$o"/></td>
     <td style="font-size:12px">{{ optional($o->processing_at)->diffForHumans() }}</td>
     <td style="text-align:right">
         <div style="display:inline-flex;gap:4px;justify-content:flex-end">
````

### B26. `resources/views/packing/index.blade.php`

````diff
@@ -12,10 +12,7 @@
     <form method="GET" class="admin-card" style="margin-bottom:14px">
         <div class="admin-card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
             <input class="input" name="q" value="{{ request('q') }}" placeholder="Order # · name · consignment · tracking" style="width:300px">
-            <select class="input" name="store_id" onchange="this.form.submit()" style="width:auto">
-                <option value="">All stores</option>
-                @foreach($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id')==$s->id)>{{ ($s->dfid?$s->dfid.' · ':'').($s->business_name ?? $s->name) }}</option>@endforeach
-            </select>
+            <x-store-search :stores="$stores"/>
             <button class="btn btn-dark">Apply</button>
             <span style="margin-left:auto;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--a-text-2)">
                 Label size
@@ -45,8 +42,8 @@
                 <div class="atable-wrap">
                     <table class="atable">
                         <thead><tr>
-                            <th style="width:36px"><input type="checkbox" id="pack-all" style="accent-color:var(--a-accent)"></th>
-                            <th>Order</th><th>Store</th><th>Customer</th><th>Courier</th><th>Consignment ID</th><th>Tracking</th><th>Booked</th><th></th>
+                            <th style="width:36px"><x-order-pick-all id="pack-all"/></th>
+                            <th>Order</th><th>Store</th><th>Customer</th><th>Items</th><th>Courier</th><th>Consignment ID</th><th>Tracking</th><th>Booked</th><th></th>
                         </tr></thead>
                         <tbody id="pack-tbody">
                             @include('packing._rows')
````

### B27. `resources/views/packing/_rows.blade.php`

````diff
@@ -1,10 +1,11 @@
 @foreach($orders as $o)
 @php $c = $o->consignments->last(); @endphp
 <tr>
-    <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" class="pack-sel" style="accent-color:var(--a-accent)"></td>
+    <td><x-order-pick :order="$o" name="order_ids[]" class="pack-sel"/></td>
     <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a></td>
     <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
     <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->shipping_city }}</div></td>
+    <td><x-order-items :order="$o"/></td>
     <td style="font-size:12px"><strong>{{ strtoupper($c?->courier_slug ?? '—') }}</strong></td>
     <td style="font-family:ui-monospace,monospace;font-size:12.5px;font-weight:700">
         @if($c?->consignment_id)
````

### B28. `resources/views/dispatch/index.blade.php`

````diff
@@ -2,7 +2,7 @@
     @section('title', 'Dispatch')
     <div class="admin-page-header">
         <div><h1>Dispatch manifest</h1><p class="sub">Packed parcels grouped by courier — select single or bulk, hand over, print.</p></div>
-        <a href="{{ route('dispatch.csv') }}" class="btn btn-outline">Export CSV (all)</a>
+        <a href="{{ route('dispatch.csv', request()->only('store')) }}" class="btn btn-outline">Export CSV ({{ request('store') ? 'filtered' : 'all' }})</a>
     </div>
 
     <x-scan-bar
@@ -10,9 +10,25 @@
         placeholder="Scan or paste Order #, Consignment ID, or Tracking code → marks parcel as Dispatched"
         hint="One per line for bulk handover."/>
 
+    <form method="GET" class="admin-card" style="margin-bottom:14px">
+        <div class="admin-card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
+            <x-store-search/>
+            <button class="btn btn-dark">Apply</button>
+            @if(request()->hasAny(['store','store_id']))
+                <a href="{{ route('dispatch.index') }}" class="btn btn-ghost">Clear</a>
+            @endif
+        </div>
+    </form>
+
     @php $total = $orders->sum(fn($g) => $g->count()); @endphp
     @if($total === 0)
-        <div class="admin-card"><x-admin.empty icon="truck" title="No parcels packed" description="Pack orders to add them to this manifest."/></div>
+        <div class="admin-card">
+            @if(request()->hasAny(['store','store_id']))
+                <x-admin.empty icon="search" title="No packed parcels for this store" description="Change or clear the store search above."/>
+            @else
+                <x-admin.empty icon="truck" title="No parcels packed" description="Pack orders to add them to this manifest."/>
+            @endif
+        </div>
     @else
         <div class="admin-card" style="margin-bottom:14px">
             <div class="admin-card-body" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
````

### B29. `resources/views/dispatch/_groups.blade.php`

````diff
@@ -2,13 +2,13 @@
 <div class="admin-card" style="margin-bottom:14px">
     <div class="admin-card-head">
         <x-admin.section-head icon="truck" :title="strtoupper($courier)" :description="$rows->count().' parcel(s)'"/>
-        <a href="{{ route('dispatch.csv', ['courier'=>$courier]) }}" class="btn btn-outline btn-sm">CSV</a>
+        <a href="{{ route('dispatch.csv', ['courier'=>$courier] + request()->only('store')) }}" class="btn btn-outline btn-sm">CSV</a>
     </div>
     <div class="atable-wrap">
         <table class="atable">
             <thead><tr>
-                <th style="width:36px"><input type="checkbox" class="disp-group-all" data-courier="{{ $courier }}" style="accent-color:var(--a-accent)"></th>
-                <th>Order</th><th>Store</th><th>Consignment ID</th><th>Tracking</th><th>Recipient</th><th style="text-align:right">COD</th><th></th>
+                <th style="width:36px"><x-order-pick-all class="disp-group-all" :data-courier="$courier"/></th>
+                <th>Order</th><th>Store</th><th>Items</th><th>Consignment ID</th><th>Tracking</th><th>Recipient</th><th style="text-align:right">COD</th><th></th>
             </tr></thead>
             <tbody>
             @foreach($rows as $o)
@@ -19,9 +19,10 @@
                     $trk  = $c?->trackingUrl();
                 @endphp
                 <tr>
-                    <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" class="disp-sel" data-courier="{{ $courier }}" style="accent-color:var(--a-accent)"></td>
+                    <td><x-order-pick :order="$o" name="order_ids[]" class="disp-sel" :data-courier="$courier"/></td>
                     <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:600;text-decoration:none">{{ $o->order_number }}</a></td>
                     <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
+                    <td><x-order-items :order="$o"/></td>
                     <td style="font-family:ui-monospace,monospace;font-size:12.5px;font-weight:700">
                         @if($hasCid) {{ $c->consignment_id }}
                         @else <span title="Waiting for the courier API to return the consignment ID" style="display:inline-block;font-family:'Poppins',sans-serif;font-size:10px;font-weight:700;color:#92400e;background:#fef3c7;border-radius:4px;padding:1px 6px">PENDING API</span>
````

### B30. `resources/views/returns/index.blade.php`

````diff
@@ -31,6 +31,17 @@
         </form>
     </div>
 
+    <form method="GET" action="{{ route('returns.index') }}" class="admin-card" style="margin-bottom:14px">
+        <div class="admin-card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
+            @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
+            <x-store-search/>
+            <button class="btn btn-dark">Apply</button>
+            @if(request()->hasAny(['store','store_id']))
+                <a href="{{ route('returns.index', request()->only('status')) }}" class="btn btn-ghost">Clear</a>
+            @endif
+        </div>
+    </form>
+
     <div id="returns-live-region">
         @include('returns._region')
     </div>
````

### B31. `resources/views/returns/_region.blade.php`

````diff
@@ -12,7 +12,7 @@
     @endphp
     @foreach($chips as $val => $c)
         @php $active = (string)request('status') === (string)$val; @endphp
-        <a href="{{ route('returns.index', $val !== '' ? ['status'=>$val] : []) }}"
+        <a href="{{ route('returns.index', array_filter(['status' => $val, 'store' => request('store')], fn ($v) => (string) $v !== '')) }}"
            style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;padding:6px 12px;border-radius:999px;text-decoration:none;border:1px solid {{ $active ? 'var(--a-accent)' : 'var(--a-border)' }};background:{{ $active ? 'var(--a-accent)' : '#fff' }};color:{{ $active ? '#fff' : 'var(--a-text-2)' }}">
             {{ $c[0] }}
             <span style="font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;background:{{ $active ? 'rgba(255,255,255,.25)' : 'var(--a-surface-2)' }}">{{ $c[1] }}</span>
@@ -25,6 +25,7 @@
     <div class="admin-card-head">
         <x-admin.section-head icon="rotate" title="Returns workflow" :description="$orders->total().' result(s)'"/>
         <form method="GET" action="{{ route('returns.index') }}">
+            @if(request('store'))<input type="hidden" name="store" value="{{ request('store') }}">@endif
             <select name="status" class="input" onchange="this.form.submit()" style="width:auto">
                 <option value="">All return states</option>
                 @foreach([
@@ -43,13 +44,15 @@
     @else
         <div class="atable-wrap">
             <table class="atable">
-                <thead><tr><th>Order</th><th>Store</th><th>Customer</th><th>Reason</th><th>Status</th><th></th></tr></thead>
+                <thead><tr><th style="width:36px"><x-order-pick-all/></th><th>Order</th><th>Store</th><th>Customer</th><th>Items</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                 <tbody>
                 @foreach($orders as $o)
                     <tr>
+                        <td><x-order-pick :order="$o"/></td>
                         <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a></td>
                         <td><strong>{{ optional($o->store)->dfid }}</strong></td>
                         <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div></td>
+                        <td><x-order-items :order="$o"/></td>
                         <td>{{ $o->return_reason ? ucwords(str_replace('_',' ',$o->return_reason)) : '—' }}</td>
                         <td><x-admin.pill :status="$o->status" :label="$o->statusLabel()"/></td>
                         <td style="text-align:right">
````

### B32. `resources/views/refunds/index.blade.php`

````diff
@@ -21,7 +21,7 @@
         @endphp
         @foreach($statuses as $val => $c)
             @php $active = (string)request('status') === (string)$val; @endphp
-            <a href="{{ route('refunds.index', $val !== '' ? ['status' => $val] : []) }}"
+            <a href="{{ route('refunds.index', array_filter(['status' => $val, 'store' => request('store')], fn ($v) => (string) $v !== '')) }}"
                style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;padding:6px 12px;border-radius:999px;text-decoration:none;border:1px solid {{ $active ? 'var(--a-accent)' : 'var(--a-border)' }};background:{{ $active ? 'var(--a-accent)' : '#fff' }};color:{{ $active ? '#fff' : 'var(--a-text-2)' }}">
                 {{ $c[0] }}
                 <span style="font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;background:{{ $active ? 'rgba(255,255,255,.25)' : 'var(--a-surface-2)' }}">{{ $c[1] }}</span>
@@ -32,6 +32,14 @@
     <div class="admin-card">
         <div class="admin-card-head">
             <x-admin.section-head icon="reports" title="Refund log" :description="$refunds->total().' result(s)'"/>
+            <form method="GET" action="{{ route('refunds.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
+                @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
+                <x-store-search/>
+                <button class="btn btn-dark">Apply</button>
+                @if(request()->hasAny(['store','store_id']))
+                    <a href="{{ route('refunds.index', request()->only('status')) }}" class="btn btn-ghost">Clear</a>
+                @endif
+            </form>
         </div>
 
         @if($refunds->isEmpty())
@@ -41,9 +49,11 @@
                 <table class="atable">
                     <thead>
                         <tr>
+                            <th style="width:36px"><x-order-pick-all/></th>
                             <th>Order</th>
                             <th>Store</th>
                             <th>Customer</th>
+                            <th>Items</th>
                             <th style="text-align:right">Grand total</th>
                             <th style="text-align:right">Refund amount</th>
                             <th>Status</th>
@@ -64,6 +74,7 @@
                             };
                         @endphp
                         <tr>
+                            <td><x-order-pick :order="$o"/></td>
                             <td>
                                 <a href="{{ route('orders.show', $o) }}"
                                    style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a>
@@ -74,6 +85,7 @@
                                 {{ $o->customer_name }}
                                 <div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div>
                             </td>
+                            <td><x-order-items :order="$o"/></td>
                             <td style="text-align:right">৳{{ number_format($o->grand_total, 2) }}</td>
                             <td style="text-align:right;font-weight:700">
                                 ৳{{ number_format($o->refund_amount ?? 0, 2) }}
````

## Section C — Test file (optional, live server e lage na)

### C1. `tests/Feature/AllOrdersItemsColumnTest.php`

````php
<?php

namespace Tests\Feature;

use App\Models\OrderMirror;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * All Orders shows each order's products with their quantity right in the
 * table, so staff don't have to open every order to see what's in it.
 */
class AllOrdersItemsColumnTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'roles' => [User::ROLE_ADMIN], 'is_active' => true,
        ]);

        $this->store = Store::create([
            'dfid' => 'DFSNK001', 'business_name' => 'Sneaker Lab BD', 'name' => 'sneakerlab',
            'base_url' => 'http://sneakerlab.test', 'api_key' => 'key', 'api_secret' => 'secret',
            'webhook_secret' => 'whsec', 'is_active' => true,
        ]);
    }

    private function order(string $number, array $items): OrderMirror
    {
        $o = OrderMirror::create([
            'store_id' => $this->store->id, 'order_number' => $number,
            'status' => 'pending', 'placed_at' => now(),
        ]);
        foreach ($items as $it) {
            $o->items()->create($it + ['unit_price' => 100, 'line_total' => 100 * $it['qty']]);
        }
        return $o;
    }

    private function cellFor(string $html, string $orderNumber): string
    {
        // The row of the given order, from its order-number link to the row end.
        $start = strpos($html, '>'.$orderNumber.'</a>');
        $this->assertNotFalse($start, "row for $orderNumber not found");
        return substr($html, $start, strpos($html, '</tr>', $start) - $start);
    }

    public function test_table_has_items_column(): void
    {
        $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 1]]);

        $this->actingAs($this->admin)->get(route('orders.index'))
            ->assertOk()
            ->assertSee('<th>Items</th>', false);
    }

    public function test_every_product_is_shown_with_its_quantity(): void
    {
        $this->order('ORD-1', [
            ['name' => 'Air Runner', 'variant_label' => 'Size 42', 'qty' => 2],
            ['name' => 'Sports Socks', 'qty' => 3],
        ]);

        $row = $this->cellFor($this->actingAs($this->admin)->get(route('orders.index'))->getContent(), 'ORD-1');

        $this->assertStringContainsString('<span class="oi-qty">2×</span>', $row);
        $this->assertStringContainsString('Air Runner<small>Size 42</small>', $row);
        $this->assertStringContainsString('<span class="oi-qty">3×</span>', $row);
        $this->assertStringContainsString('Sports Socks', $row);
    }

    public function test_summary_shows_line_and_piece_counts(): void
    {
        $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 2], ['name' => 'Sports Socks', 'qty' => 3]]);
        $this->order('ORD-2', [['name' => 'Canvas Slip-on', 'qty' => 1]]);

        $html = $this->actingAs($this->admin)->get(route('orders.index'))->getContent();

        $this->assertStringContainsString('2 items · 5 pcs', $this->cellFor($html, 'ORD-1'));
        $this->assertStringContainsString('1 item · 1 pc', $this->cellFor($html, 'ORD-2'));
    }

    public function test_long_orders_show_three_items_then_a_more_link(): void
    {
        $order = $this->order('ORD-1', array_map(fn ($i) => ['name' => "Product $i", 'qty' => 1], range(1, 5)));

        $row = $this->cellFor($this->actingAs($this->admin)->get(route('orders.index'))->getContent(), 'ORD-1');

        $this->assertStringContainsString('5 items · 5 pcs', $row);
        $this->assertSame(3, substr_count($row, 'class="oi-qty"'));
        $this->assertStringContainsString('+2 more', $row);
        $this->assertStringContainsString(route('orders.show', $order), $row);
    }

    public function test_full_name_is_available_on_hover(): void
    {
        $this->order('ORD-1', [['name' => 'Tracksuit Set — Navy M', 'variant_label' => 'Slim', 'qty' => 2]]);

        $row = $this->cellFor($this->actingAs($this->admin)->get(route('orders.index'))->getContent(), 'ORD-1');

        $this->assertStringContainsString('title="Tracksuit Set — Navy M · Slim × 2"', $row);
    }

    public function test_items_stay_with_their_own_order(): void
    {
        $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 2]]);
        $this->order('ORD-2', [['name' => 'Canvas Slip-on', 'qty' => 7]]);

        $html = $this->actingAs($this->admin)->get(route('orders.index'))->getContent();

        $this->assertStringContainsString('Air Runner', $this->cellFor($html, 'ORD-1'));
        $this->assertStringNotContainsString('Canvas Slip-on', $this->cellFor($html, 'ORD-1'));
        $this->assertStringContainsString('Canvas Slip-on', $this->cellFor($html, 'ORD-2'));
        $this->assertStringContainsString('<span class="oi-qty">7×</span>', $this->cellFor($html, 'ORD-2'));
    }

    public function test_order_without_items_shows_a_dash(): void
    {
        $this->order('ORD-1', []);

        $row = $this->cellFor($this->actingAs($this->admin)->get(route('orders.index'))->getContent(), 'ORD-1');

        $this->assertStringNotContainsString('oi-qty', $row);
        $this->assertStringNotContainsString('oi-summary', $row);
        $this->assertStringContainsString('—', $row);
    }

    public function test_live_refresh_rows_also_show_items(): void
    {
        $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 2]]);

        $this->actingAs($this->admin)->get(route('orders.rows'))
            ->assertOk()
            ->assertSee('<th>Items</th>', false)
            ->assertSee('Air Runner')
            ->assertSee('<span class="oi-qty">2×</span>', false);
    }

    public function test_items_are_loaded_without_a_query_per_order(): void
    {
        foreach (range(1, 10) as $i) {
            $this->order("ORD-$i", [['name' => "Item $i", 'qty' => $i]]);
        }

        \DB::enableQueryLog();
        $this->actingAs($this->admin)->get(route('orders.index'))->assertOk();
        $itemQueries = collect(\DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'order_items_mirror'))
            ->count();

        $this->assertSame(1, $itemQueries, 'items must be eager-loaded in one query, not one per order');
    }
}
````

### C2. `tests/Feature/AllOrdersStoreSearchTest.php`

````php
<?php

namespace Tests\Feature;

use App\Models\OrderMirror;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * All Orders → Store box is a free-text search: whatever the user types is
 * matched against the store's DFID, business name and name (no need to pick
 * from a dropdown). Every word typed must match.
 */
class AllOrdersStoreSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'roles' => [User::ROLE_ADMIN], 'is_active' => true,
        ]);

        $sneaker = $this->store('DFSNK001', 'Sneaker Lab BD', 'sneakerlab');
        $trend   = $this->store('DFTRD001', 'TrendBD Fashion', 'trendbd');
        $plain   = $this->store(null, null, 'Corner Shop');

        $this->order($sneaker, 'SNK-1');
        $this->order($sneaker, 'SNK-2');
        $this->order($trend, 'TRD-1');
        $this->order($plain, 'CRN-1');
    }

    private function store(?string $dfid, ?string $business, string $name): Store
    {
        return Store::create([
            'dfid'           => $dfid,
            'business_name'  => $business,
            'name'           => $name,
            'base_url'       => 'http://'.strtolower(str_replace(' ', '', $name)).'.test',
            'api_key'        => 'key-'.$name,
            'api_secret'     => 'secret',
            'webhook_secret' => 'whsec',
            'is_active'      => true,
        ]);
    }

    private function order(Store $store, string $number): void
    {
        OrderMirror::create([
            'store_id'      => $store->id,
            'order_number'  => $number,
            'status'        => 'pending',
            'customer_name' => 'Customer '.$number,
            'placed_at'     => now(),
        ]);
    }

    /** Order numbers shown on /orders for the given store search, sorted. */
    private function ordersFor(?string $store): array
    {
        $res = $this->actingAs($this->admin)->get(route('orders.index', $store === null ? [] : ['store' => $store]));
        $res->assertOk();

        $numbers = $res->viewData('orders')->pluck('order_number')->all();
        sort($numbers);
        return $numbers;
    }

    public function test_no_store_search_shows_every_store(): void
    {
        $this->assertSame(['CRN-1', 'SNK-1', 'SNK-2', 'TRD-1'], $this->ordersFor(null));
        $this->assertSame(['CRN-1', 'SNK-1', 'SNK-2', 'TRD-1'], $this->ordersFor('   '));
    }

    public function test_search_by_full_store_id(): void
    {
        $this->assertSame(['SNK-1', 'SNK-2'], $this->ordersFor('DFSNK001'));
        $this->assertSame(['TRD-1'], $this->ordersFor('DFTRD001'));
    }

    public function test_search_by_partial_store_id_is_case_insensitive(): void
    {
        $this->assertSame(['SNK-1', 'SNK-2'], $this->ordersFor('dfsnk'));
        $this->assertSame(['TRD-1'], $this->ordersFor('trd0'));
    }

    public function test_search_by_business_name(): void
    {
        $this->assertSame(['SNK-1', 'SNK-2'], $this->ordersFor('Sneaker Lab'));
        $this->assertSame(['TRD-1'], $this->ordersFor('fashion'));
    }

    public function test_search_by_store_name_when_there_is_no_business_name(): void
    {
        $this->assertSame(['CRN-1'], $this->ordersFor('corner'));
    }

    public function test_full_label_from_the_suggestion_list_matches_its_store(): void
    {
        $this->assertSame(['SNK-1', 'SNK-2'], $this->ordersFor('DFSNK001 · Sneaker Lab BD'));
    }

    public function test_every_word_must_match_the_same_store(): void
    {
        // "bd" alone is in both Sneaker Lab BD and TrendBD …
        $this->assertSame(['SNK-1', 'SNK-2', 'TRD-1'], $this->ordersFor('bd'));
        // … but "trend lab" matches no single store.
        $this->assertSame([], $this->ordersFor('trend lab'));
    }

    public function test_unknown_store_returns_no_orders(): void
    {
        $this->assertSame([], $this->ordersFor('zzz'));
        $this->actingAs($this->admin)->get(route('orders.index', ['store' => 'zzz']))
            ->assertOk()
            ->assertSee('0 order(s) match', false);
    }

    public function test_search_box_keeps_what_was_typed(): void
    {
        $this->actingAs($this->admin)->get(route('orders.index', ['store' => 'dfsnk']))
            ->assertOk()
            ->assertSee('name="store"', false)
            ->assertSee('value="dfsnk"', false);
    }

    public function test_store_search_combines_with_other_filters(): void
    {
        OrderMirror::where('order_number', 'SNK-2')->update(['payment_method' => 'bkash']);

        $res = $this->actingAs($this->admin)->get(route('orders.index', ['store' => 'sneaker', 'payment_method' => 'bkash']));
        $this->assertSame(['SNK-2'], $res->viewData('orders')->pluck('order_number')->all());
    }

    public function test_old_store_id_links_still_work(): void
    {
        $trend = Store::where('dfid', 'DFTRD001')->first();

        $res = $this->actingAs($this->admin)->get(route('orders.index', ['store_id' => $trend->id]));
        $this->assertSame(['TRD-1'], $res->viewData('orders')->pluck('order_number')->all());
    }

    public function test_live_refresh_rows_respect_the_store_search(): void
    {
        $this->actingAs($this->admin)->get(route('orders.rows', ['store' => 'trend']))
            ->assertOk()
            ->assertSee('TRD-1')
            ->assertDontSee('SNK-1')
            ->assertDontSee('CRN-1');
    }

    public function test_csv_export_respects_the_store_search(): void
    {
        $csv = $this->actingAs($this->admin)->get(route('orders.export', ['store' => 'DFSNK']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('SNK-1', $csv);
        $this->assertStringContainsString('SNK-2', $csv);
        $this->assertStringNotContainsString('TRD-1', $csv);
        $this->assertStringNotContainsString('CRN-1', $csv);
    }

    public function test_guests_cannot_search_orders(): void
    {
        $this->get(route('orders.index', ['store' => 'dfsnk']))->assertRedirect(route('login'));
    }
}
````

### C3. `tests/Feature/OrderListPagesTest.php`

````php
<?php

namespace Tests\Feature;

use App\Models\CourierConsignment;
use App\Models\CustomerMirror;
use App\Models\OrderMirror;
use App\Models\Store;
use App\Models\User;
use App\Services\Orders\OrderStateMachine as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every order list in the OMS has the same three things:
 *  - a free-text store search (DFID / store name),
 *  - each order's products × quantity,
 *  - a checkbox per order carrying its product / piece counts for the selection bar.
 * Each page is checked here with an order from two different stores.
 */
class OrderListPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Store $sneaker;
    private Store $trend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'roles' => [User::ROLE_ADMIN], 'is_active' => true,
        ]);
        $this->sneaker = $this->store('DFSNK001', 'Sneaker Lab BD', 'sneakerlab');
        $this->trend   = $this->store('DFTRD001', 'TrendBD Fashion', 'trendbd');
    }

    /**
     * page => [index route, AJAX rows route|null, how to make an order show up there].
     * Pages without a store search (dashboard, customer page) are covered separately.
     */
    public static function pages(): array
    {
        return [
            'all orders'   => ['orders.index',       'orders.rows',       ['status' => 'pending']],
            'inbox'        => ['inbox.index',        null,                ['status' => 'pending']],
            'my orders'    => ['orders-new.index',   null,                ['status' => S::PENDING_VERIFICATION, 'placed_by_admin' => true]],
            'verification' => ['verification.index', 'verification.rows', ['status' => S::PENDING_VERIFICATION]],
            'processing'   => ['processing.index',   'processing.rows',   ['status' => S::PROCESSING]],
            'packing'      => ['packing.index',      'packing.rows',      ['status' => S::PROCESSING, 'consignment' => true]],
            'dispatch'     => ['dispatch.index',     'dispatch.rows',     ['status' => S::PACKED, 'consignment' => true]],
            'returns'      => ['returns.index',      'returns.rows',      ['status' => S::RETURN_PENDING, 'return_pending_at' => true]],
            'refunds'      => ['refunds.index',      null,                ['status' => S::RETURNED, 'refund_status' => 'pending']],
        ];
    }

    /** The pages whose table is refreshed in place over AJAX. */
    public static function pagesWithRows(): array
    {
        return array_filter(self::pages(), fn ($p) => $p[1] !== null);
    }

    private function store(string $dfid, string $business, string $name): Store
    {
        return Store::create([
            'dfid' => $dfid, 'business_name' => $business, 'name' => $name,
            'base_url' => "http://$name.test", 'api_key' => "key-$name", 'api_secret' => 'secret',
            'webhook_secret' => 'whsec', 'is_active' => true,
        ]);
    }

    private function order(Store $store, string $number, array $page, array $items): OrderMirror
    {
        $o = OrderMirror::create(array_filter([
            'store_id'          => $store->id,
            'order_number'      => $number,
            'status'            => $page['status'],
            'customer_name'     => "Customer $number",
            'customer_phone'    => '01700000000',
            'customer_email'    => 'buyer@test.local',
            'placed_at'         => now(),
            'placed_by_user_id' => ($page['placed_by_admin'] ?? false) ? $this->admin->id : null,
            'return_pending_at' => ($page['return_pending_at'] ?? false) ? now() : null,
            'refund_status'     => $page['refund_status'] ?? null,
        ], fn ($v) => $v !== null));

        foreach ($items as $it) {
            $o->items()->create($it + ['unit_price' => 100, 'line_total' => 100 * $it['qty']]);
        }
        if ($page['consignment'] ?? false) {
            CourierConsignment::create([
                'order_id' => $o->id, 'courier_slug' => 'steadfast', 'courier_name' => 'Steadfast',
                'consignment_id' => "CID-$number", 'tracking_code' => "TRK-$number", 'booked_at' => now(),
            ]);
        }
        return $o;
    }

    /** Two orders, one per store: SNK-1 (2× Air Runner + 3× Sports Socks) and TRD-1 (1× Denim Jacket). */
    private function seedOrders(array $page): array
    {
        return [
            $this->order($this->sneaker, 'SNK-1', $page, [
                ['name' => 'Air Runner', 'variant_label' => 'Size 42', 'qty' => 2],
                ['name' => 'Sports Socks', 'qty' => 3],
            ]),
            $this->order($this->trend, 'TRD-1', $page, [['name' => 'Denim Jacket', 'qty' => 1]]),
        ];
    }

    /** Page HTML; JSON row responses ({tbody, pagination} / {html}) are flattened to their HTML. */
    private function html(string $route, array $query = []): string
    {
        $res = $this->actingAs($this->admin)->get(route($route, $query));
        $res->assertOk();
        $json = json_decode($res->getContent(), true);
        return is_array($json) ? implode("\n", array_filter($json, 'is_string')) : $res->getContent();
    }

    private function pick(string $html, OrderMirror $o): string
    {
        $this->assertSame(1, preg_match('/<input type="checkbox" value="'.$o->id.'" data-order-pick[^>]*>/', $html, $m),
            "no checkbox for {$o->order_number}");
        return $m[0];
    }

    // ── items × quantity ─────────────────────────────────────────────────

    #[DataProvider('pages')]
    public function test_page_shows_each_orders_products_with_quantity(string $route, ?string $rows, array $page): void
    {
        $this->seedOrders($page);

        $html = $this->html($route);

        $this->assertStringContainsString('<th>Items</th>', $html);
        $this->assertStringContainsString('2 items · 5 pcs', $html);
        $this->assertStringContainsString('<span class="oi-qty">2×</span>', $html);
        $this->assertStringContainsString('Air Runner<small>Size 42</small>', $html);
        $this->assertStringContainsString('<span class="oi-qty">3×</span>', $html);
        $this->assertStringContainsString('1 item · 1 pc', $html);
        $this->assertStringContainsString('Denim Jacket', $html);
    }

    #[DataProvider('pages')]
    public function test_page_loads_items_in_one_query(string $route, ?string $rows, array $page): void
    {
        foreach (range(1, 6) as $i) {
            $this->order($i % 2 ? $this->sneaker : $this->trend, "ORD-$i", $page, [['name' => "Item $i", 'qty' => $i]]);
        }

        DB::enableQueryLog();
        $this->html($route);
        $itemQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'order_items_mirror'))->count();

        $this->assertLessThanOrEqual(1, $itemQueries, 'items must be eager-loaded, not queried per order');
    }

    // ── selection checkbox ───────────────────────────────────────────────

    #[DataProvider('pages')]
    public function test_page_has_a_checkbox_per_order_with_its_counts(string $route, ?string $rows, array $page): void
    {
        [$snk, $trd] = $this->seedOrders($page);

        $html = $this->html($route);

        $this->assertStringContainsString('data-lines="2" data-pcs="5"', $this->pick($html, $snk));
        $this->assertStringContainsString('data-lines="1" data-pcs="1"', $this->pick($html, $trd));
        $this->assertStringContainsString('data-order-pick-all', $html);
        $this->assertStringContainsString('id="order-pick-bar"', $html);
    }

    // ── store search ─────────────────────────────────────────────────────

    #[DataProvider('pages')]
    public function test_store_search_by_id_or_name_filters_the_page(string $route, ?string $rows, array $page): void
    {
        $this->seedOrders($page);

        foreach (['DFSNK001', 'dfsnk', 'Sneaker Lab', 'DFSNK001 · Sneaker Lab BD'] as $term) {
            $html = $this->html($route, ['store' => $term]);
            $this->assertStringContainsString('SNK-1', $html, "'$term' should find SNK-1");
            $this->assertStringNotContainsString('TRD-1', $html, "'$term' should hide TRD-1");
        }

        $html = $this->html($route, ['store' => 'trendbd fashion']);
        $this->assertStringContainsString('TRD-1', $html);
        $this->assertStringNotContainsString('SNK-1', $html);
    }

    #[DataProvider('pages')]
    public function test_unknown_store_shows_no_orders(string $route, ?string $rows, array $page): void
    {
        $this->seedOrders($page);

        $html = $this->html($route, ['store' => 'zzz']);

        $this->assertStringNotContainsString('SNK-1', $html);
        $this->assertStringNotContainsString('TRD-1', $html);
    }

    #[DataProvider('pages')]
    public function test_store_search_box_is_shown_and_keeps_what_was_typed(string $route, ?string $rows, array $page): void
    {
        $html = $this->html($route, ['store' => 'dfsnk']);

        $this->assertStringContainsString('data-store-search', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="store"[^>]*value="dfsnk"/', $html);
    }

    #[DataProvider('pages')]
    public function test_old_store_id_links_still_filter(string $route, ?string $rows, array $page): void
    {
        $this->seedOrders($page);

        $html = $this->html($route, ['store_id' => $this->trend->id]);

        $this->assertStringContainsString('TRD-1', $html);
        $this->assertStringNotContainsString('SNK-1', $html);
    }

    // ── live refresh (AJAX rows) ─────────────────────────────────────────

    #[DataProvider('pagesWithRows')]
    public function test_live_refresh_rows_keep_store_search_items_and_checkboxes(string $route, string $rows, array $page): void
    {
        [$snk] = $this->seedOrders($page);

        $html = $this->html($rows, ['store' => 'sneaker']);

        $this->assertStringContainsString('SNK-1', $html);
        $this->assertStringNotContainsString('TRD-1', $html);
        $this->assertStringContainsString('<span class="oi-qty">2×</span>', $html);
        $this->assertStringContainsString('data-pcs="5"', $this->pick($html, $snk));
    }

    // ── pages without a store search ─────────────────────────────────────

    public function test_dashboard_recent_orders_show_items_and_checkboxes(): void
    {
        [$snk, $trd] = $this->seedOrders(['status' => 'pending']);

        $html = $this->html('dashboard');

        $this->assertStringContainsString('2 items · 5 pcs', $html);
        $this->assertStringContainsString('Denim Jacket', $html);
        $this->assertStringContainsString('data-pcs="5"', $this->pick($html, $snk));
        $this->assertStringContainsString('data-pcs="1"', $this->pick($html, $trd));
    }

    public function test_customer_order_history_shows_items_and_checkboxes(): void
    {
        [$snk] = $this->seedOrders(['status' => 'pending']);
        $customer = CustomerMirror::create([
            'store_id' => $this->sneaker->id, 'customer_id' => 1,
            'name' => 'Buyer', 'email' => 'buyer@test.local', 'phone' => '01700000000',
        ]);

        $html = $this->html('customers.show', ['customer' => $customer]);

        $this->assertStringContainsString('2 items · 5 pcs', $html);
        $this->assertStringContainsString('data-pcs="5"', $this->pick($html, $snk));
        $this->assertStringNotContainsString('TRD-1', $html);   // other store's order isn't this customer's
    }
}
````

### C4. `tests/Feature/OrderPickTest.php`

````php
<?php

namespace Tests\Feature;

use App\Models\OrderMirror;
use App\Models\Store;
use App\Models\User;
use App\Services\Orders\OrderStateMachine as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Order lists have a checkbox per order; ticking orders shows how many orders,
 * products and pieces are selected. The checkbox carries the order's counts.
 */
class OrderPickTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'roles' => [User::ROLE_ADMIN], 'is_active' => true,
        ]);

        $this->store = Store::create([
            'dfid' => 'DFSNK001', 'business_name' => 'Sneaker Lab BD', 'name' => 'sneakerlab',
            'base_url' => 'http://sneakerlab.test', 'api_key' => 'key', 'api_secret' => 'secret',
            'webhook_secret' => 'whsec', 'is_active' => true,
        ]);
    }

    private function order(string $number, array $items, string $status = 'pending'): OrderMirror
    {
        $o = OrderMirror::create([
            'store_id' => $this->store->id, 'order_number' => $number,
            'status' => $status, 'placed_at' => now(),
        ]);
        foreach ($items as $it) {
            $o->items()->create($it + ['unit_price' => 100, 'line_total' => 100 * $it['qty']]);
        }
        return $o;
    }

    /** The order's row checkbox tag. */
    private function pickFor(string $html, OrderMirror $o): string
    {
        $this->assertSame(1, preg_match('/<input type="checkbox" value="'.$o->id.'" data-order-pick[^>]*>/', $html, $m),
            "checkbox for {$o->order_number} not found");
        return $m[0];
    }

    public function test_all_orders_rows_carry_product_and_piece_counts(): void
    {
        $a = $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 2], ['name' => 'Sports Socks', 'qty' => 3]]);
        $b = $this->order('ORD-2', [['name' => 'Canvas Slip-on', 'qty' => 1]]);

        $html = $this->actingAs($this->admin)->get(route('orders.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-lines="2" data-pcs="5"', $this->pickFor($html, $a));
        $this->assertStringContainsString('data-lines="1" data-pcs="1"', $this->pickFor($html, $b));
        $this->assertStringContainsString('data-order-pick-all', $html);
        $this->assertStringContainsString('id="order-pick-bar"', $html);
    }

    public function test_checkbox_lists_the_products_for_the_details_panel(): void
    {
        $o = $this->order('ORD-1', [
            ['name' => 'Air Runner', 'variant_label' => 'Size 42', 'qty' => 2],
            ['name' => 'Sports Socks', 'qty' => 3],
        ]);

        $pick = $this->pickFor($this->actingAs($this->admin)->get(route('orders.index'))->getContent(), $o);

        $this->assertSame(1, preg_match('/data-items="([^"]*)"/', $pick, $m));
        $this->assertSame([['Air Runner', 'Size 42', 2], ['Sports Socks', null, 3]],
            json_decode(html_entity_decode($m[1]), true));
    }

    public function test_order_without_items_counts_zero(): void
    {
        $o = $this->order('ORD-1', []);

        $html = $this->actingAs($this->admin)->get(route('orders.index'))->getContent();

        $this->assertStringContainsString('data-lines="0" data-pcs="0"', $this->pickFor($html, $o));
    }

    public function test_live_refresh_rows_keep_the_checkboxes(): void
    {
        $o = $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 4]]);

        $html = $this->actingAs($this->admin)->get(route('orders.rows'))->assertOk()->getContent();

        $this->assertStringContainsString('data-lines="1" data-pcs="4"', $this->pickFor($html, $o));
    }

    public function test_other_order_lists_have_the_checkbox(): void
    {
        $o = $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 2]]);

        foreach (['inbox.index', 'dashboard'] as $route) {
            $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('data-pcs="2"', $this->pickFor($html, $o), $route);
        }
    }

    public function test_bulk_action_checkboxes_still_submit_order_ids(): void
    {
        $o = $this->order('ORD-1', [['name' => 'Air Runner', 'qty' => 3]], S::PENDING_VERIFICATION);

        $pick = $this->pickFor($this->actingAs($this->admin)->get(route('verification.index'))->assertOk()->getContent(), $o);

        $this->assertStringContainsString('name="order_ids[]"', $pick);
        $this->assertStringContainsString('class="row-sel"', $pick);
        $this->assertStringContainsString('data-pcs="3"', $pick);
    }
}
````

### C5. `tests/Feature/StoreFilterScopeTest.php`

````php
<?php

namespace Tests\Feature;

use App\Models\OrderMirror;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The query pieces behind every order list's store search:
 * Store::matching(), OrderMirror::storeFilter() and Store::forPicker().
 */
class StoreFilterScopeTest extends TestCase
{
    use RefreshDatabase;

    private Store $sneaker;
    private Store $trend;
    private Store $corner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sneaker = $this->store('DFSNK001', 'Sneaker Lab BD', 'sneakerlab');
        $this->trend   = $this->store('DFTRD001', 'TrendBD Fashion', 'trendbd');
        $this->corner  = $this->store(null, null, 'Corner Shop');

        foreach ([$this->sneaker, $this->trend, $this->corner] as $s) {
            OrderMirror::create(['store_id' => $s->id, 'order_number' => 'ORD-'.$s->id, 'status' => 'pending', 'placed_at' => now()]);
        }
    }

    private function store(?string $dfid, ?string $business, string $name): Store
    {
        return Store::create([
            'dfid' => $dfid, 'business_name' => $business, 'name' => $name,
            'base_url' => 'http://'.str_replace(' ', '', strtolower($name)).'.test', 'api_key' => "key-$name",
            'api_secret' => 'secret', 'webhook_secret' => 'whsec', 'is_active' => true,
        ]);
    }

    private function matching(?string $term): array
    {
        return Store::matching($term)->orderBy('id')->pluck('id')->all();
    }

    private function filtered($storeId, ?string $term): array
    {
        return OrderMirror::storeFilter($storeId, $term)->orderBy('id')->pluck('store_id')->all();
    }

    public function test_matching_finds_by_dfid_business_name_or_name(): void
    {
        $this->assertSame([$this->sneaker->id], $this->matching('DFSNK001'));
        $this->assertSame([$this->sneaker->id], $this->matching('snk'));
        $this->assertSame([$this->trend->id], $this->matching('Fashion'));
        $this->assertSame([$this->corner->id], $this->matching('corner'));   // no DFID / business name
    }

    public function test_matching_needs_every_word_in_the_same_store(): void
    {
        $this->assertSame([$this->sneaker->id, $this->trend->id], $this->matching('bd'));
        $this->assertSame([$this->sneaker->id], $this->matching('bd lab'));
        $this->assertSame([], $this->matching('trend lab'));
    }

    public function test_matching_accepts_the_full_suggestion_label(): void
    {
        $this->assertSame([$this->trend->id], $this->matching('DFTRD001 · TrendBD Fashion'));
    }

    public function test_blank_search_matches_every_store(): void
    {
        $all = [$this->sneaker->id, $this->trend->id, $this->corner->id];
        $this->assertSame($all, $this->matching(null));
        $this->assertSame($all, $this->matching('   '));
    }

    public function test_store_filter_by_text(): void
    {
        $this->assertSame([$this->sneaker->id], $this->filtered(null, 'sneaker'));
        $this->assertSame([], $this->filtered(null, 'zzz'));
    }

    public function test_store_filter_by_old_store_id(): void
    {
        $this->assertSame([$this->trend->id], $this->filtered($this->trend->id, null));
    }

    public function test_store_filter_with_id_and_text_needs_both(): void
    {
        $this->assertSame([$this->trend->id], $this->filtered($this->trend->id, 'fashion'));
        $this->assertSame([], $this->filtered($this->trend->id, 'sneaker'));
    }

    public function test_store_filter_without_input_keeps_every_order(): void
    {
        $this->assertSame([$this->sneaker->id, $this->trend->id, $this->corner->id], $this->filtered(null, null));
        $this->assertSame([$this->sneaker->id, $this->trend->id, $this->corner->id], $this->filtered('', ''));
    }

    public function test_picker_lists_stores_by_business_name_with_only_the_needed_columns(): void
    {
        $stores = Store::forPicker();

        $this->assertSame(['id', 'dfid', 'business_name', 'name'], array_keys($stores->first()->getAttributes()));
        $this->assertSame(
            Store::orderBy('business_name')->pluck('id')->all(),
            $stores->pluck('id')->all(),
        );
    }
}
````

### C6. `tests/Unit/OrderComponentsTest.php`

````php
<?php

namespace Tests\Unit;

use App\Models\OrderItemMirror;
use App\Models\OrderMirror;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The Blade components shared by every order list, rendered on their own with
 * in-memory orders (no database):
 *  - <x-order-items>    products × quantity cell
 *  - <x-order-pick>     row checkbox carrying the order's counts / products
 *  - <x-order-pick-all> header "select all" checkbox
 */
class OrderComponentsTest extends TestCase
{
    /** An unsaved order with the given [name, qty, variant?] lines. */
    private function order(array $lines, int $id = 7): OrderMirror
    {
        $order = (new OrderMirror)->forceFill(['id' => $id, 'order_number' => "ORD-$id"]);
        $order->exists = true;
        $order->setRelation('items', collect($lines)->map(fn ($l) => (new OrderItemMirror)->forceFill([
            'name' => $l[0], 'qty' => $l[1], 'variant_label' => $l[2] ?? null,
        ])));
        return $order;
    }

    private function render(string $tag, OrderMirror $order): string
    {
        return Blade::render($tag, ['order' => $order]);
    }

    // ── <x-order-items> ──────────────────────────────────────────────────

    public function test_items_lists_every_product_with_its_quantity(): void
    {
        $html = $this->render('<x-order-items :order="$order"/>', $this->order([['Air Runner', 2, 'Size 42'], ['Sports Socks', 3]]));

        $this->assertStringContainsString('2 items · 5 pcs', $html);
        $this->assertStringContainsString('<span class="oi-qty">2×</span>', $html);
        $this->assertStringContainsString('Air Runner<small>Size 42</small>', $html);
        $this->assertStringContainsString('<span class="oi-qty">3×</span>', $html);
        $this->assertStringContainsString('Sports Socks', $html);
    }

    public function test_items_summary_uses_singular_for_one(): void
    {
        $html = $this->render('<x-order-items :order="$order"/>', $this->order([['Cap', 1]]));

        $this->assertStringContainsString('1 item · 1 pc', $html);
    }

    public function test_items_full_name_is_in_the_hover_title(): void
    {
        $html = $this->render('<x-order-items :order="$order"/>', $this->order([['Tracksuit Set', 2, 'Navy M']]));

        $this->assertStringContainsString('title="Tracksuit Set · Navy M × 2"', $html);
    }

    public function test_items_shows_three_lines_then_a_more_link_to_the_order(): void
    {
        $order = $this->order(array_map(fn ($i) => ["Product $i", 1], range(1, 5)));

        $html = $this->render('<x-order-items :order="$order"/>', $order);

        $this->assertSame(3, substr_count($html, 'class="oi-qty"'));
        $this->assertStringNotContainsString('Product 4', $html);
        $this->assertStringContainsString('+2 more', $html);
        $this->assertStringContainsString('href="'.route('orders.show', $order).'"', $html);
    }

    public function test_items_more_link_and_limit_can_be_changed(): void
    {
        $order = $this->order(array_map(fn ($i) => ["Product $i", 1], range(1, 5)));

        $html = Blade::render('<x-order-items :order="$order" :limit="4" href="/custom"/>', ['order' => $order]);

        $this->assertSame(4, substr_count($html, 'class="oi-qty"'));
        $this->assertStringContainsString('+1 more', $html);
        $this->assertStringContainsString('href="/custom"', $html);
    }

    public function test_items_shows_a_dash_for_an_order_without_items(): void
    {
        $html = $this->render('<x-order-items :order="$order"/>', $this->order([]));

        $this->assertStringContainsString('—', $html);
        $this->assertStringNotContainsString('oi-summary', $html);
        $this->assertStringNotContainsString('oi-qty', $html);
    }

    // ── <x-order-pick> ───────────────────────────────────────────────────

    public function test_pick_carries_the_order_id_and_counts(): void
    {
        $html = $this->render('<x-order-pick :order="$order"/>', $this->order([['Air Runner', 2], ['Sports Socks', 3]], 42));

        $this->assertStringContainsString('type="checkbox" value="42" data-order-pick', $html);
        $this->assertStringContainsString('data-lines="2" data-pcs="5"', $html);
        $this->assertStringContainsString('aria-label="Select order ORD-42"', $html);
    }

    public function test_pick_lists_the_products_for_the_details_panel(): void
    {
        $html = $this->render('<x-order-pick :order="$order"/>', $this->order([['Air Runner', 2, 'Size 42'], ['Socks "Pro"', 3]]));

        $this->assertSame(1, preg_match('/data-items="([^"]*)"/', $html, $m));
        $this->assertSame([['Air Runner', 'Size 42', 2], ['Socks "Pro"', null, 3]], json_decode(html_entity_decode($m[1]), true));
    }

    public function test_pick_for_an_order_without_items_counts_zero(): void
    {
        $html = $this->render('<x-order-pick :order="$order"/>', $this->order([]));

        $this->assertStringContainsString('data-lines="0" data-pcs="0"', $html);
        $this->assertStringContainsString('data-items="[]"', $html);
    }

    public function test_pick_keeps_bulk_form_attributes(): void
    {
        $html = $this->render('<x-order-pick :order="$order" name="order_ids[]" class="row-sel" data-courier="steadfast"/>', $this->order([['Cap', 1]]));

        $this->assertStringContainsString('name="order_ids[]"', $html);
        $this->assertStringContainsString('class="row-sel"', $html);
        $this->assertStringContainsString('data-courier="steadfast"', $html);
        $this->assertStringContainsString('style="accent-color:var(--a-accent)"', $html);
    }

    // ── <x-order-pick-all> ───────────────────────────────────────────────

    public function test_pick_all_is_a_marked_header_checkbox(): void
    {
        $html = Blade::render('<x-order-pick-all id="sel-all"/>');

        $this->assertStringContainsString('type="checkbox" data-order-pick-all', $html);
        $this->assertStringContainsString('id="sel-all"', $html);
        $this->assertStringNotContainsString('value=', $html);   // never submitted / restored as an order id
    }
}
````

### C7. `tests/Unit/StoreSearchWordsTest.php`

````php
<?php

namespace Tests\Unit;

use App\Models\Store;
use PHPUnit\Framework\TestCase;

/**
 * The All Orders store search splits what the user typed into words; every
 * word must then match the store's DFID or name.
 */
class StoreSearchWordsTest extends TestCase
{
    public function test_splits_on_spaces(): void
    {
        $this->assertSame(['Sneaker', 'Lab', 'BD'], Store::searchWords('Sneaker Lab BD'));
    }

    public function test_a_full_store_label_splits_on_the_middle_dot(): void
    {
        $this->assertSame(['DFSNK001', 'Sneaker', 'Lab', 'BD'], Store::searchWords('DFSNK001 · Sneaker Lab BD'));
        $this->assertSame(['DFSNK001', 'Sneaker'], Store::searchWords('DFSNK001·Sneaker'));
    }

    public function test_trims_and_collapses_extra_whitespace(): void
    {
        $this->assertSame(['dfsnk', 'lab'], Store::searchWords("  dfsnk \t  lab  "));
    }

    public function test_a_single_partial_id_stays_one_word(): void
    {
        $this->assertSame(['dfsnk'], Store::searchWords('dfsnk'));
    }

    public function test_blank_input_means_no_store_filter(): void
    {
        $this->assertSame([], Store::searchWords(null));
        $this->assertSame([], Store::searchWords(''));
        $this->assertSame([], Store::searchWords('   '));
        $this->assertSame([], Store::searchWords(' · '));
    }
}
````

