<x-app-layout>
    @section('title', 'Exchange #'.$exchange->id)
    <div class="admin-page-header">
        <div><h1>Exchange · {{ optional($exchange->original)->order_number }} ⇄ {{ optional($exchange->replacement)->order_number ?? '—' }}</h1><p class="sub"><x-admin.pill :status="$exchange->status"/></p></div>
        <a href="{{ route('exchanges.index') }}" class="btn btn-outline">← Back</a>
    </div>

    @if(session('status'))<div class="admin-flash admin-flash-ok">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="admin-flash admin-flash-err">{{ session('error') }}</div>@endif

    @if(! in_array($exchange->status, ['completed','cancelled'], true))
        <div class="admin-card" style="margin-bottom:14px;background:var(--a-surface-2)">
            <div class="admin-card-body" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <div style="font-size:13px;color:var(--a-text-2)">
                    <strong>Old product back?</strong>
                    @if($exchange->original && in_array($exchange->original->status, ['returned','restockable','damaged'], true))
                        <span style="color:var(--a-success,#059669)">✓ received ({{ $exchange->original->statusLabel() }})</span>
                    @else
                        <span style="color:#92400e">not yet — receive &amp; inspect it on the Returns page first</span>
                    @endif
                </div>
                <div style="margin-left:auto;display:flex;gap:8px">
                    <form method="POST" action="{{ route('exchanges.cancel', $exchange) }}" onsubmit="return confirm('Cancel this exchange and its replacement order?')">
                        @csrf<button class="btn btn-outline btn-sm">Cancel exchange</button>
                    </form>
                    <form method="POST" action="{{ route('exchanges.complete', $exchange) }}" onsubmit="return confirm('Release the replacement order to Processing and close the exchange?')">
                        @csrf<button class="btn btn-dark btn-sm">Complete · release replacement</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="cart" title="Original order"/></div>
            <div class="admin-card-body" style="font-size:13px">
                @if($exchange->original)
                    <a href="{{ route('orders.show', $exchange->original) }}">{{ $exchange->original->order_number }}</a><br>
                    {{ $exchange->original->customer_name }}<br>
                    Status: <x-admin.pill :status="$exchange->original->status" :label="$exchange->original->statusLabel()"/>
                    <ul style="margin-top:6px">@foreach($exchange->original->items as $i)<li>{{ $i->qty }}× {{ $i->name }}</li>@endforeach</ul>
                @endif
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="package" title="Replacement order"/></div>
            <div class="admin-card-body" style="font-size:13px">
                @if($exchange->replacement)
                    <a href="{{ route('orders.show', $exchange->replacement) }}">{{ $exchange->replacement->order_number }}</a><br>
                    Status: <x-admin.pill :status="$exchange->replacement->status" :label="$exchange->replacement->statusLabel()"/>
                    <ul style="margin-top:6px">@foreach($exchange->replacement->items as $i)<li>{{ $i->qty }}× {{ $i->name }}</li>@endforeach</ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
