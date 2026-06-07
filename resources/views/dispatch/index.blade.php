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
        <div class="admin-card" style="margin-bottom:14px">
            <div class="admin-card-body" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <span style="font-size:12px;font-weight:600;color:var(--a-text-2)">Label size</span>
                <select id="lbl-size" class="input" style="width:auto;height:34px" onchange="localStorage.setItem('dfoms_label_size',this.value)">
                    <option value="2x3">2" × 3"</option>
                    <option value="3x3">3" × 3"</option>
                </select>
            </div>
        </div>

        <form method="POST" id="disp-form" action="{{ route('dispatch.bulk-handover') }}">
            @csrf
            <div class="admin-card" id="disp-bar" style="display:none;background:var(--a-surface-2)">
                <div class="admin-card-body" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <strong id="disp-count" style="font-size:14px"></strong>
                    <button type="button" class="btn btn-outline" onclick="printSelectedDisp()">Print labels</button>
                    <button type="submit" class="btn btn-dark">Mark selected Dispatched</button>
                </div>
            </div>

            <div id="dispatch-live-region">
                @include('dispatch._groups')
            </div>
        </form>
    @endif

    @push('scripts')
    <script>
    (function(){
        // Restore persisted label size choice
        var sel = document.getElementById('lbl-size');
        if (sel) sel.value = localStorage.getItem('dfoms_label_size') || '2x3';

        function labelSize() { return document.getElementById('lbl-size')?.value || '2x3'; }

        const form = document.getElementById('disp-form');
        if (!form) return;
        const bar = document.getElementById('disp-bar');
        const cnt = document.getElementById('disp-count');
        function refresh(){
            const n = form.querySelectorAll('.disp-sel:checked').length;
            if (bar) bar.style.display = n ? '' : 'none';
            if (cnt) cnt.textContent = n + ' selected';
        }
        // Delegated on form — survives region replacement by AJAX refresh
        form.addEventListener('change', function(e) {
            if (e.target.classList.contains('disp-group-all')) {
                form.querySelectorAll('.disp-sel[data-courier="'+e.target.dataset.courier+'"]').forEach(c => c.checked = e.target.checked);
                refresh();
            } else if (e.target.classList.contains('disp-sel')) {
                refresh();
            }
        });
        form.addEventListener('submit', function(e) {
            const isBulk = !e.submitter || !e.submitter.hasAttribute('formaction');
            if (isBulk && !form.querySelectorAll('.disp-sel:checked').length) {
                e.preventDefault(); alert('Select at least one parcel first.');
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
    function printSelectedDisp(){
        const ids = [...document.querySelectorAll('#disp-form .disp-sel:checked')].map(c => c.value);
        if (!ids.length) { alert('Select at least one parcel first.'); return; }
        const size = document.getElementById('lbl-size')?.value || '2x3';
        window.open('{{ route('labels.batch') }}?ids=' + ids.join(',') + '&size=' + size, '_blank');
    }
    </script>
    @endpush

    <x-order-sync
        scope="dispatch"
        :rows-url="route('dispatch.rows')"
        mode="region"
        region-id="dispatch-live-region"/>
</x-app-layout>
