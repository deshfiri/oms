@props([
    'action',                 // POST URL for the scan submit
    'placeholder' => 'Search or scan by Order #, Consignment ID, or Tracking code',
    'hint'        => null,    // optional sub-line below the bar
])
<form method="POST" action="{{ $action }}" class="admin-card scan-bar-card" style="margin-bottom:14px">
    @csrf
    <div class="admin-card-body" style="display:flex;gap:10px;align-items:stretch">
        <input
            name="codes"
            autocomplete="off"
            autofocus
            placeholder="{{ $placeholder }}"
            class="input scan-bar-input"
            style="flex:1;font-family:ui-monospace,monospace;font-size:14px">
        <button class="btn btn-dark" style="min-width:120px">Scan</button>
    </div>
    @if($hint)
        <div class="admin-card-body" style="padding-top:0;font-size:12px;color:var(--a-text-3)">{{ $hint }}</div>
    @endif
</form>
