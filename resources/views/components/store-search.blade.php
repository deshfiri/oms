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
