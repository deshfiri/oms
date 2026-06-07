<x-app-layout>
    @section('title', 'Pending Order')
    <div class="admin-page-header">
        <div><h1>Pending Order</h1><p class="sub"><span id="verf-total">{{ $orders->total() }}</span> order(s) awaiting customer confirmation.</p></div>
    </div>

    <div class="admin-card">
        <div class="admin-card-head">
            <x-admin.section-head icon="phone" title="Pending Verification" description="Call the customer · edit the order · confirm or cancel."/>
            <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Order # · name · phone" class="input" style="width:240px">
                <select name="store_id" class="input" onchange="this.form.submit()" style="width:auto">
                    <option value="">All stores</option>
                    @foreach($stores as $s)<option value="{{ $s->id }}" @selected(request('store_id')==$s->id)>{{ ($s->dfid?$s->dfid.' · ':'').($s->business_name ?? $s->name) }}</option>@endforeach
                </select>
                <button class="btn btn-dark">Apply</button>
            </form>
        </div>

        @if($orders->isEmpty())
            <x-admin.empty icon="check" title="Inbox clear" description="No orders waiting for verification."/>
        @else
            <form method="POST" id="bulk-form">
                @csrf
                <div class="admin-card-body" id="bulk-bar" style="display:none;background:var(--a-surface-2);border-bottom:1px solid var(--a-border);align-items:center;gap:10px;flex-wrap:wrap">
                    <strong id="sel-count" style="font-size:14px"></strong>
                    <button type="button" class="btn btn-dark btn-sm" onclick="submitBulk('{{ route('verification.bulk-confirm') }}')">Confirm selected</button>
                    <span style="border-left:1px solid var(--a-border);height:26px"></span>
                    <select class="input" name="reason" style="width:auto;height:34px">
                        @foreach(\App\Models\OrderMirror::CANCEL_REASONS as $rs)
                            <option value="{{ $rs }}">{{ ucwords(str_replace('_',' ',$rs)) }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-danger btn-sm" onclick="if(confirm('Cancel the selected orders?')) submitBulk('{{ route('verification.bulk-cancel') }}')">Cancel selected</button>
                </div>

                <div class="atable-wrap">
                    <table class="atable">
                        <thead><tr>
                            <th style="width:36px"><input type="checkbox" id="sel-all" style="accent-color:var(--a-accent)"></th>
                            <th>Order</th><th>Store</th><th>Customer</th><th>Placed</th><th style="text-align:right">Total</th><th></th>
                        </tr></thead>
                        <tbody id="verf-tbody">
                            @include('verification._rows')
                        </tbody>
                    </table>
                </div>
            </form>
            <div id="verf-pagination" class="admin-pagination">{{ $orders->links() }}</div>
        @endif
    </div>

    @push('scripts')
    <script>
    (function(){
        function refresh(){
            const form = document.getElementById('bulk-form');
            const bar  = document.getElementById('bulk-bar');
            const cnt  = document.getElementById('sel-count');
            if (!form || !bar) return;
            const n = form.querySelectorAll('.row-sel:checked').length;
            bar.style.display = n ? 'flex' : 'none';
            if (cnt) cnt.textContent = n + ' selected';
        }
        // Delegated on document — survives tbody replacement by AJAX refresh
        document.addEventListener('change', function(e) {
            if (e.target.id === 'sel-all') {
                const form = document.getElementById('bulk-form');
                form?.querySelectorAll('.row-sel').forEach(c => c.checked = e.target.checked);
                refresh();
            } else if (e.target.classList.contains('row-sel')) {
                refresh();
            }
        });
    })();
    function submitBulk(url){
        const form = document.getElementById('bulk-form');
        if (!form.querySelectorAll('.row-sel:checked').length) { alert('Select at least one order.'); return; }
        form.action = url; form.submit();
    }
    </script>
    @endpush

    <x-order-sync
        scope="verification"
        :rows-url="route('verification.rows')"
        mode="tbody"
        tbody-id="verf-tbody"
        pagination-id="verf-pagination"
        total-id="verf-total"/>
</x-app-layout>
