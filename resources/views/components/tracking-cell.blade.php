@props(['code' => null, 'url' => null])
{{-- Tracking code (copyable) + a "Track" link to the courier's official tracking
     page. We copy the CODE, not a link, because couriers don't expose a working
     per-parcel URL — the code is what you paste into the tracking page. The copy
     handler is the delegated [data-copy] listener in layouts/app.blade.php. --}}
@if($code || $url)
<span style="display:inline-flex;align-items:center;gap:6px;max-width:100%">
    @if($code)
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $code }}</span>
        <button type="button" data-copy="{{ $code }}" title="Copy tracking code"
                style="flex:none;border:1px solid var(--a-border,#e2e8f0);background:transparent;border-radius:4px;padding:1px 6px;font-size:10px;line-height:1.6;cursor:pointer;color:var(--a-text-3);font-family:'Poppins',sans-serif">Copy</button>
    @endif
    @if($url)<a href="{{ $url }}" target="_blank" rel="noopener" title="Open the courier tracking page"
               style="flex:none;font-family:'Poppins',sans-serif;font-size:10px;white-space:nowrap">Track ↗</a>@endif
</span>
@else
—
@endif
