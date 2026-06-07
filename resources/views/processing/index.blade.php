<x-app-layout>
    @section('title', 'Processing')
    <div class="admin-page-header">
        <div><h1>Processing</h1><p class="sub"><span id="proc-total">{{ $orders->total() }}</span> confirmed order(s) ready to send to the courier. Booking a courier prints the label and moves the order to Packing.</p></div>
    </div>

    <form method="GET" class="admin-card" style="margin-bottom:14px">
        <div class="admin-card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Order # · name · phone" style="width:280px">
            <select class="input" name="store_id" onchange="this.form.submit()" style="width:auto">
                <option value="">All stores</option>
                @foreach($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id')==$s->id)>{{ ($s->dfid?$s->dfid.' · ':'').($s->business_name ?? $s->name) }}</option>@endforeach
            </select>
            <button class="btn btn-dark">Apply</button>
        </div>
    </form>

    <form method="POST" id="bulk-form" action="{{ route('processing.bulk-pack') }}">
        @csrf
        <div class="admin-card" id="bulk-bar" style="display:none;background:var(--a-surface-2)">
            <div class="admin-card-body" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <strong id="sel-count" style="font-size:14px"></strong>
                <span style="font-size:11px;color:var(--a-text-2)">Courier: <strong>Auto</strong> (via storefront)</span>
                <button type="submit" class="btn btn-dark">Send to courier &amp; print labels</button>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-head"><x-admin.section-head icon="package" title="Ready to send"/></div>
            @if($orders->isEmpty())
                <x-admin.empty icon="check" title="Nothing in processing" description="Confirmed orders appear here automatically."/>
            @else
                <div class="atable-wrap">
                    <table class="atable">
                        <thead>
                            <tr>
                                <th style="width:36px"><input type="checkbox" id="sel-all" style="accent-color:var(--a-accent)"></th>
                                <th>Order</th><th>Store</th><th>Customer</th><th>Items</th><th>Aged</th><th></th>
                            </tr>
                        </thead>
                        <tbody id="proc-tbody">
                            @include('processing._rows')
                        </tbody>
                    </table>
                </div>
                <div id="proc-pagination" class="admin-pagination">{{ $orders->links() }}</div>
            @endif
        </div>
    </form>

    @push('scripts')
    <script>
    (function(){
        function refresh(){
            const form = document.getElementById('bulk-form');
            const bar  = document.getElementById('bulk-bar');
            const cnt  = document.getElementById('sel-count');
            if (!form || !bar) return;
            const n = form.querySelectorAll('.row-sel:checked').length;
            bar.style.display = n ? '' : 'none';
            if (cnt) cnt.textContent = n + ' selected';
        }
        // Delegated — survives tbody replacement
        document.addEventListener('change', function(e) {
            if (e.target.id === 'sel-all') {
                document.getElementById('bulk-form')?.querySelectorAll('.row-sel').forEach(c => c.checked = e.target.checked);
                refresh();
            } else if (e.target.classList.contains('row-sel')) {
                refresh();
            }
        });
        document.addEventListener('submit', function(e) {
            if (e.target.id !== 'bulk-form') return;
            const isBulk = !e.submitter || !e.submitter.hasAttribute('formaction');
            if (isBulk && !e.target.querySelectorAll('.row-sel:checked').length) {
                e.preventDefault(); alert('Select at least one order first.');
            }
        });
    })();
    </script>
    @endpush

    <x-order-sync
        scope="processing"
        :rows-url="route('processing.rows')"
        mode="tbody"
        tbody-id="proc-tbody"
        pagination-id="proc-pagination"
        total-id="proc-total"/>
</x-app-layout>
