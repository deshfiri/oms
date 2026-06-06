<x-app-layout>
    @section('title', 'Count #'.$count->id)
    <div class="admin-page-header"><div><h1>Stock count #{{ $count->id }}</h1><p class="sub"><x-admin.pill :status="$count->status"/> · {{ $count->location ?? '—' }}</p></div></div>

    @if($count->status === 'open')
        <form method="POST" action="{{ route('stock-counts.line', $count) }}" class="admin-card" style="margin-bottom:14px">
            @csrf
            <div class="admin-card-body" style="display:flex;gap:8px">
                <input name="sku" autofocus placeholder="Scan SKU" autocomplete="off" class="input" style="flex:1;font-family:ui-monospace,monospace">
                <input name="counted_qty" type="number" min="0" value="1" class="input" style="width:90px">
                <input name="notes" placeholder="Notes" class="input" style="flex:1">
                <button class="btn btn-dark">Add</button>
            </div>
        </form>
    @endif

    <div class="admin-card">
        <div class="admin-card-head"><x-admin.section-head icon="list" title="Count lines"/></div>
        @if($count->lines->isEmpty())
            <x-admin.empty icon="scan" title="Scan items to start"/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>SKU</th><th style="text-align:right">Expected</th><th style="text-align:right">Counted</th><th style="text-align:right">Variance</th><th>Notes</th></tr></thead>
                    <tbody>
                    @foreach($count->lines as $l)
                        <tr style="{{ $l->variance!==0 ? 'background:rgba(217,119,6,.05)' : '' }}">
                            <td style="font-family:ui-monospace,monospace;font-size:12px">{{ $l->sku }}</td>
                            <td style="text-align:right">{{ $l->expected_qty }}</td>
                            <td style="text-align:right">{{ $l->counted_qty }}</td>
                            <td style="text-align:right;font-weight:700;color:{{ $l->variance < 0 ? 'var(--a-danger,#dc2626)' : ($l->variance > 0 ? 'var(--a-success,#119264)' : 'var(--a-text)') }}">{{ $l->variance }}</td>
                            <td style="font-size:12px;color:var(--a-text-3)">{{ $l->notes }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($count->status === 'open' && $count->lines->isNotEmpty())
        <form method="POST" action="{{ route('stock-counts.complete', $count) }}" style="margin-top:14px" onsubmit="return confirm('Push counted quantities to the storefront?')">
            @csrf
            <button class="btn btn-dark">Complete & push to storefront</button>
        </form>
    @endif
</x-app-layout>
