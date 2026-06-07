<x-app-layout>
    @section('title', 'Returns')
    <div class="admin-page-header">
        <div><h1>Returns</h1><p class="sub">{{ $orders->total() }} order(s) across return states.</p></div>
    </div>

    <x-scan-bar
        :action="route('scan.return')"
        placeholder="Scan or paste Order #, Consignment ID, or Tracking code → marks parcel as Returned"
        hint="One per line for bulk intake."/>

    {{-- Warehouse-initiated return: move a delivered/shipped order into the return flow --}}
    <div class="admin-card" style="margin-bottom:14px">
        <div class="admin-card-head"><x-admin.section-head icon="rotate" title="Start a return" description="Mark a delivered or in-transit order as returned by the customer."/></div>
        <form method="POST" action="{{ route('returns.start') }}" class="admin-card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            @csrf
            <label style="flex:1;min-width:200px"><span style="font-size:12px;font-weight:600">Order number *</span>
                <input class="input" name="order_number" required placeholder="e.g. DF-2026-235124" autocomplete="off">
            </label>
            <label style="flex:1;min-width:200px"><span style="font-size:12px;font-weight:600">Reason</span>
                <select class="input" name="reason">
                    <option value="customer_return">Customer return</option>
                    <option value="wrong_item">Wrong item</option>
                    <option value="damaged_on_arrival">Damaged on arrival</option>
                    <option value="size_issue">Size / fit issue</option>
                    <option value="changed_mind">Changed mind</option>
                    <option value="delivery_failed">Delivery failed</option>
                </select>
            </label>
            <button class="btn btn-dark">Start return</button>
        </form>
    </div>

    <div id="returns-live-region">
        @include('returns._region')
    </div>

    <x-order-sync
        scope="returns"
        :rows-url="route('returns.rows')"
        mode="region"
        region-id="returns-live-region"/>
</x-app-layout>
