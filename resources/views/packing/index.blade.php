<x-app-layout>
    @section('title', 'Packing')
    <div class="admin-page-header">
        <div><h1>Packing</h1><p class="sub"><span id="pack-total">{{ $orders->total() }}</span> order(s) consigned with a courier — print the label, pack the parcel, mark Packed.</p></div>
    </div>

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
            <span style="margin-left:auto;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--a-text-2)">
                Label size
                <select id="lbl-size" class="input" style="width:auto;height:34px" onchange="localStorage.setItem('dfoms_label_size',this.value)">
                    <option value="2x3">2" × 3"</option>
                    <option value="3x3">3" × 3"</option>
                </select>
            </span>
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
                        <tbody id="pack-tbody">
                            @include('packing._rows')
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
        <div id="pack-pagination" class="admin-pagination">{{ $orders->links() }}</div>
    @endif

    @push('scripts')
    <script>
    (function(){
        // Restore persisted label size choice
        var sel = document.getElementById('lbl-size');
        if (sel) sel.value = localStorage.getItem('dfoms_label_size') || '2x3';

        function labelSize() { return document.getElementById('lbl-size')?.value || '2x3'; }

        function refresh(){
            const form = document.getElementById('pack-form');
            const bar  = document.getElementById('pack-bar');
            const cnt  = document.getElementById('pack-count');
            if (!form || !bar) return;
            const n = form.querySelectorAll('.pack-sel:checked').length;
            bar.style.display = n ? '' : 'none';
            if (cnt) cnt.textContent = n + ' selected';
        }
        document.addEventListener('change', function(e) {
            if (e.target.id === 'pack-all') {
                document.getElementById('pack-form')?.querySelectorAll('.pack-sel').forEach(c => c.checked = e.target.checked);
                refresh();
            } else if (e.target.classList.contains('pack-sel')) {
                refresh();
            }
        });
        document.addEventListener('submit', function(e) {
            if (e.target.id !== 'pack-form') return;
            const isBulk = !e.submitter || !e.submitter.hasAttribute('formaction');
            if (isBulk && !e.target.querySelectorAll('.pack-sel:checked').length) {
                e.preventDefault(); alert('Select at least one order first.');
            }
        });

        // Intercept individual label links — append current size before opening
        document.addEventListener('click', function(e) {
            var link = e.target.closest('a.js-label-link');
            if (!link) return;
            e.preventDefault();
            var url = link.href + (link.href.includes('?') ? '&' : '?') + 'size=' + labelSize();
            window.open(url, '_blank');
        });
    })();
    function printSelected(){
        const ids = [...document.querySelectorAll('#pack-form .pack-sel:checked')].map(c => c.value);
        if (!ids.length) { alert('Select at least one order first.'); return; }
        const size = document.getElementById('lbl-size')?.value || '2x3';
        window.open('{{ route('labels.batch') }}?ids=' + ids.join(',') + '&size=' + size, '_blank');
    }
    </script>
    @endpush

    <x-order-sync
        scope="packing"
        :rows-url="route('packing.rows')"
        mode="tbody"
        tbody-id="pack-tbody"
        pagination-id="pack-pagination"
        total-id="pack-total"/>
</x-app-layout>
