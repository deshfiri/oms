@props([
    'title' => 'Nothing here yet',
    'description' => null,
    'ctaLabel' => null,
    'ctaUrl' => null,
    'icon' => 'box',
])
<div style="padding:48px 24px;text-align:center">
    <div style="width:56px;height:56px;border-radius:var(--a-r-lg);background:var(--a-surface-2);color:var(--a-text-3);display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px">
        {!! \App\View\Icons::svg($icon, 26) !!}
    </div>
    <p style="font-size:15px;font-weight:700;color:var(--a-text);margin:0 0 4px">{{ $title }}</p>
    @if($description)<p style="font-size:13px;color:var(--a-text-2);max-width:380px;margin:0 auto 14px">{{ $description }}</p>@endif
    @if($ctaLabel && $ctaUrl)
        <a href="{{ $ctaUrl }}" class="btn btn-dark">{{ $ctaLabel }}</a>
    @endif
</div>
