<x-app-layout>
    @section('title', 'Dispatch')
    <div class="admin-page-header">
        <div><h1>Dispatch manifest</h1><p class="sub">Packed parcels grouped by courier — select single or bulk, hand over, print.</p></div>
        <a href="{{ route('dispatch.csv') }}" class="btn btn-outline">Export CSV (all)</a>
    </div>

    <x-scan-bar
        :action="route('scan.dispatch')"
        placeholder="Scan or paste Order #, Consignment ID, or Tracking code → marks parcel as Dispatched"
        hint="One per line for bulk handover."/>

    @php $total = $orders->sum(fn($g) => $g->count()); @endphp
    @if($total === 0)
        <div class="admin-card"><x-admin.empty icon="truck" title="No parcels packed" description="Pack orders to add them to this manifest."/></div>
    @else
        <form method="POST" id="disp-form" action="{{ route('dispatch.bulk-handover') }}">
            @csrf
            <div class="admin-card" id="disp-bar" style="display:none;background:var(--a-surface-2)">
                <div class="admin-card-body" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <strong id="disp-count" style="font-size:14px"></strong>
                    <button type="button" class="btn btn-outline" onclick="printSelectedDisp()">Print labels</button>
                    <button type="submit" class="btn btn-dark">Mark selected Dispatched</button>
                </div>
            </div>

            @foreach($orders as $courier => $rows)
                <div class="admin-card" style="margin-bottom:14px">
                    <div class="admin-card-head">
                        <x-admin.section-head icon="truck" :title="strtoupper($courier)" :description="$rows->count().' parcel(s)'"/>
                        <a href="{{ route('dispatch.csv', ['courier'=>$courier]) }}" class="btn btn-outline btn-sm">CSV</a>
                    </div>
                    <div class="atable-wrap">
                        <table class="atable">
                            <thead><tr>
                                <th style="width:36px"><input type="checkbox" class="disp-group-all" data-courier="{{ $courier }}" style="accent-color:var(--a-accent)"></th>
                                <th>Order</th><th>Store</th><th>Consignment ID</th><th>Tracking</th><th>Recipient</th><th style="text-align:right">COD</th><th></th>
                            </tr></thead>
                            <tbody>
                            @foreach($rows as $o)
                                @php
                                    $c    = $o->consignments->last();
                                    $caps = $courierCaps[$c?->courier_slug ?? $courier] ?? ['provides_tracking'=>true];
                                    $hasCid = $c && $c->consignment_id;
                                @endphp
                                <tr>
                                    <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" class="disp-sel" data-courier="{{ $courier }}" style="accent-color:var(--a-accent)"></td>
                                    <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:600;text-decoration:none">{{ $o->order_number }}</a></td>
                                    <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
                                    <td style="font-family:ui-monospace,monospace;font-size:12.5px;font-weight:700">
                                        @if($hasCid)
                                            {{ $c->consignment_id }}
                                        @else
                                            <span title="Waiting for the courier API to return the consignment ID" style="display:inline-block;font-family:'Poppins',sans-serif;font-size:10px;font-weight:700;color:#92400e;background:#fef3c7;border-radius:4px;padding:1px 6px">PENDING API</span>
                                        @endif
                                    </td>
                                    <td style="font-family:ui-monospace,monospace;font-size:11px;color:var(--a-text-3)">
                                        @if($c && $c->tracking_code)
                                            {{ $c->tracking_code }}
                                        @elseif(! ($caps['provides_tracking'] ?? true))
                                            <span style="font-family:'Poppins',sans-serif;font-size:10px;color:var(--a-text-3)">no tracking</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $o->shipping_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->shipping_city }}</div></td>
                                    <td style="text-align:right;font-weight:600">৳{{ number_format($o->payment_method==='cod' ? $o->grand_total : 0, 2) }}</td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <div style="display:inline-flex;gap:4px">
                                            <a href="{{ route('labels.single', $o) }}" target="_blank" class="btn btn-outline btn-sm">Label</a>
                                            <button type="submit" formaction="{{ route('dispatch.handover', $o) }}" class="btn btn-dark btn-sm">Handed over</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </form>
    @endif

    @push('scripts')
    <script>
    (function(){
        const form = document.getElementById('disp-form');
        if (!form) return;
        const bar = document.getElementById('disp-bar');
        const cnt = document.getElementById('disp-count');
        function refresh(){
            const n = form.querySelectorAll('.disp-sel:checked').length;
            bar.style.display = n ? '' : 'none';
            cnt.textContent = n + ' selected';
        }
        // Per-courier "select all" toggles just that group
        form.querySelectorAll('.disp-group-all').forEach(master => {
            master.addEventListener('change', () => {
                form.querySelectorAll('.disp-sel[data-courier="'+master.dataset.courier+'"]').forEach(c => c.checked = master.checked);
                refresh();
            });
        });
        form.addEventListener('change', e => { if (e.target.classList.contains('disp-sel')) refresh(); });
        form.addEventListener('submit', e => {
            const isBulk = !e.submitter || !e.submitter.hasAttribute('formaction');
            if (isBulk && !form.querySelectorAll('.disp-sel:checked').length) {
                e.preventDefault(); alert('Select at least one parcel first.');
            }
        });
    })();
    function printSelectedDisp(){
        const ids = [...document.querySelectorAll('#disp-form .disp-sel:checked')].map(c => c.value);
        if (!ids.length) { alert('Select at least one parcel first.'); return; }
        window.open('{{ route('labels.batch') }}?ids=' + ids.join(','), '_blank');
    }
    </script>
    @endpush
    <x-live-refresh scope="dispatch"/>
</x-app-layout>
