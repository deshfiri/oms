@props(['items' => []])
@if(!empty($items))
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:14px">
        @foreach($items as $it)
            @php
                $tone = $it['tone'] ?? 'default';
                $color = match ($tone) {
                    'accent'  => 'var(--a-accent)',
                    'success' => 'var(--a-success)',
                    'warning' => 'var(--a-warning)',
                    'danger'  => 'var(--a-danger)',
                    default   => 'var(--a-text)',
                };
            @endphp
            <a @class(['admin-card']) href="{{ $it['href'] ?? '#' }}" style="padding:14px 16px;text-decoration:none;display:block">
                <p style="font-size:10.5px;font-weight:600;color:var(--a-text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">{{ $it['label'] }}</p>
                <p style="font-size:20px;font-weight:700;color:{{ $color }};line-height:1.1;letter-spacing:-.01em;margin:0">{{ $it['value'] }}</p>
                @if(! empty($it['sub']))<p style="font-size:11px;color:var(--a-text-3);margin-top:4px;margin-bottom:0">{{ $it['sub'] }}</p>@endif
            </a>
        @endforeach
    </div>
@endif
