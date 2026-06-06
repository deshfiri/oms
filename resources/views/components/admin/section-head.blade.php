@props(['icon' => null, 'title', 'description' => null])
@php
    $iconSvg = $icon ? \App\View\Icons::svg($icon, 18) : null;
@endphp
<div style="display:flex;align-items:center;gap:12px">
    @if($iconSvg)
        <div style="width:36px;height:36px;border-radius:var(--a-r);background:var(--a-accent-soft);color:var(--a-accent);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">
            {!! $iconSvg !!}
        </div>
    @endif
    <div>
        <p style="font-size:15px;font-weight:700;color:var(--a-text);line-height:1.3;margin:0">{{ $title }}</p>
        @if($description)<p style="font-size:12.5px;color:var(--a-text-2);margin-top:2px">{{ $description }}</p>@endif
    </div>
</div>
