@props(['column', 'label', 'align' => 'left'])
@php
    $currentSort = request('sort');
    $currentDir  = request('dir', 'asc');
    $isActive    = $currentSort === $column;
    $nextDir     = $isActive && $currentDir === 'asc' ? 'desc' : 'asc';
    $url         = request()->fullUrlWithQuery(['sort' => $column, 'dir' => $nextDir]);
@endphp
<th style="text-align:{{ $align }}">
    <a href="{{ $url }}" style="display:inline-flex;align-items:center;gap:5px;color:{{ $isActive ? 'var(--a-text)' : 'var(--a-text-2)' }};font-weight:{{ $isActive ? 700 : 600 }};text-transform:uppercase;font-size:10.5px;letter-spacing:.5px;text-decoration:none">
        {{ $label }}
        @if($isActive)
            @if($currentDir === 'asc')
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
            @else
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            @endif
        @else
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:.4"><polyline points="6 9 12 3 18 9"/><polyline points="6 15 12 21 18 15"/></svg>
        @endif
    </a>
</th>
