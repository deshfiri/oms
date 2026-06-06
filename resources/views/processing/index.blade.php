<x-app-layout>
    @section('title', 'Processing')
    <div class="admin-page-header">
        <div><h1>Processing</h1><p class="sub">{{ $orders->total() }} confirmed order(s) ready to send to the courier. Booking a courier prints the label and moves the order to Packing.</p></div>
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
        {{-- Bulk action bar — appears when one or more rows are selected --}}
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
                        <tbody>
                            @foreach($orders as $o)
                                <tr>
                                    <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" class="row-sel" style="accent-color:var(--a-accent)"></td>
                                    <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a></td>
                                    <td><strong>{{ optional($o->store)->dfid }}</strong><div style="font-size:11px;color:var(--a-text-3)">{{ optional($o->store)->business_name }}</div></td>
                                    <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->shipping_city }}</div></td>
                                    <td>{{ $o->items()->sum('qty') }}</td>
                                    <td style="font-size:12px">{{ optional($o->processing_at)->diffForHumans() }}</td>
                                    <td style="text-align:right">
                                        <div style="display:inline-flex;gap:4px;justify-content:flex-end">
                                            <a href="{{ route('orders.show', $o) }}" class="btn btn-outline btn-sm">View</a>
                                            <button type="submit" formaction="{{ route('processing.start-packing', $o) }}" class="btn btn-dark btn-sm">Send to courier</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-pagination">{{ $orders->links() }}</div>
            @endif
        </div>
    </form>

    @push('scripts')
    <script>
    (function(){
        const form = document.getElementById('bulk-form');
        if (!form) return;
        const bar  = document.getElementById('bulk-bar');
        const cnt  = document.getElementById('sel-count');
        const all  = document.getElementById('sel-all');
        function refresh(){
            const n = form.querySelectorAll('.row-sel:checked').length;
            bar.style.display = n ? '' : 'none';
            cnt.textContent = n + ' selected';
        }
        all?.addEventListener('change', () => { form.querySelectorAll('.row-sel').forEach(c => c.checked = all.checked); refresh(); });
        form.addEventListener('change', e => { if (e.target.classList.contains('row-sel')) refresh(); });
        // The bulk "Send to courier & print" button needs at least one row.
        form.addEventListener('submit', e => {
            const isBulk = !e.submitter || !e.submitter.hasAttribute('formaction'); // bulk button has no formaction
            if (isBulk && !form.querySelectorAll('.row-sel:checked').length) {
                e.preventDefault();
                alert('Select at least one order first.');
            }
        });
    })();
    </script>
    @endpush
    <x-live-refresh scope="processing"/>
</x-app-layout>
