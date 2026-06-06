<x-app-layout>
    @section('title', 'Packing')
    <div class="admin-page-header">
        <div><h1>Packing</h1><p class="sub">{{ $orders->total() }} order(s) consigned with a courier — print the label, pack the parcel, mark Packed.</p></div>
    </div>

    {{-- Scan bar: scanning the label barcode flips Processing → Packed. --}}
    <x-scan-bar
        :action="route('scan.pack')"
        placeholder="Scan label barcode → Order #, Consignment ID, or Tracking code — marks Packed"
        hint="Scan each parcel after you've sealed it. Bulk: paste one code per line."/>

    <form method="GET" class="admin-card" style="margin-bottom:14px">
        <div class="admin-card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Order # · name · consignment · tracking" style="width:300px">
            <select class="input" name="store_id" onchange="this.form.submit()" style="width:auto">
                <option value="">All stores</option>
                @foreach($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id')==$s->id)>{{ ($s->dfid?$s->dfid.' · ':'').($s->business_name ?? $s->name) }}</option>@endforeach
            </select>
            <button class="btn btn-dark">Apply</button>
        </div>
    </form>

    @if($orders->isEmpty())
        <div class="admin-card"><x-admin.empty icon="check" title="Nothing in packing" description="Send orders to the courier from the Processing queue."/></div>
    @else
        <form method="POST" id="pack-form" action="{{ route('packing.bulk-mark-packed') }}">
            @csrf
            <div class="admin-card" id="pack-bar" style="display:none;background:var(--a-surface-2)">
                <div class="admin-card-body" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <strong id="pack-count" style="font-size:14px"></strong>
                    <button type="button" class="btn btn-outline" onclick="printSelected()">Print labels</button>
                    <button type="submit" class="btn btn-dark">Mark selected Packed</button>
                </div>
            </div>

            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="package" title="Awaiting physical pack"/></div>
                <div class="atable-wrap">
                    <table class="atable">
                        <thead><tr>
                            <th style="width:36px"><input type="checkbox" id="pack-all" style="accent-color:var(--a-accent)"></th>
                            <th>Order</th><th>Store</th><th>Customer</th><th>Courier</th><th>Consignment ID</th><th>Tracking</th><th>Booked</th><th></th>
                        </tr></thead>
                        <tbody>
                            @foreach($orders as $o)
                                @php $c = $o->consignments->last(); @endphp
                                <tr>
                                    <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" class="pack-sel" style="accent-color:var(--a-accent)"></td>
                                    <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a></td>
                                    <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
                                    <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->shipping_city }}</div></td>
                                    <td style="font-size:12px"><strong>{{ strtoupper($c?->courier_slug ?? '—') }}</strong></td>
                                    <td style="font-family:ui-monospace,monospace;font-size:12.5px;font-weight:700">
                                        @if($c?->consignment_id)
                                            {{ $c->consignment_id }}
                                        @else
                                            <span title="Waiting for the courier API to return the consignment ID" style="display:inline-block;font-family:'Poppins',sans-serif;font-size:10px;font-weight:700;color:#92400e;background:#fef3c7;border-radius:4px;padding:1px 6px">PENDING API</span>
                                        @endif
                                    </td>
                                    <td style="font-family:ui-monospace,monospace;font-size:11px;color:var(--a-text-3)">{{ $c?->tracking_code ?? '—' }}</td>
                                    <td style="font-size:12px">{{ optional($c?->booked_at)->diffForHumans() ?? '—' }}</td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <div style="display:inline-flex;gap:4px">
                                            <a href="{{ route('labels.single', $o) }}" target="_blank" class="btn btn-outline btn-sm">Print label</a>
                                            <button type="submit" formaction="{{ route('packing.mark-packed', $o) }}" class="btn btn-dark btn-sm">Mark Packed</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
        <div class="admin-pagination">{{ $orders->links() }}</div>
    @endif

    @push('scripts')
    <script>
    (function(){
        const form = document.getElementById('pack-form');
        if (!form) return;
        const bar = document.getElementById('pack-bar');
        const cnt = document.getElementById('pack-count');
        const all = document.getElementById('pack-all');
        function refresh(){
            const n = form.querySelectorAll('.pack-sel:checked').length;
            bar.style.display = n ? '' : 'none';
            cnt.textContent = n + ' selected';
        }
        all?.addEventListener('change', () => { form.querySelectorAll('.pack-sel').forEach(c => c.checked = all.checked); refresh(); });
        form.addEventListener('change', e => { if (e.target.classList.contains('pack-sel')) refresh(); });
        // Bulk "Mark Packed" needs a selection
        form.addEventListener('submit', e => {
            const isBulk = !e.submitter || !e.submitter.hasAttribute('formaction');
            if (isBulk && !form.querySelectorAll('.pack-sel:checked').length) {
                e.preventDefault(); alert('Select at least one order first.');
            }
        });
    })();
    function printSelected(){
        const ids = [...document.querySelectorAll('#pack-form .pack-sel:checked')].map(c => c.value);
        if (!ids.length) { alert('Select at least one order first.'); return; }
        window.open('{{ route('labels.batch') }}?ids=' + ids.join(','), '_blank');
    }
    </script>
    @endpush

    <x-live-refresh scope="packing"/>
</x-app-layout>
