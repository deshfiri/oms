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

    @php
        $S = \App\Services\Orders\OrderStateMachine::class;
        $chips = [
            '' => ['All', $counts->sum()],
            $S::RETURN_PENDING => ['Return Pending', $counts[$S::RETURN_PENDING] ?? 0],
            $S::RETURNED => ['Returned · to inspect', $counts[$S::RETURNED] ?? 0],
            $S::AWAITING_RETURN_PRODUCT => ['Awaiting Product', $counts[$S::AWAITING_RETURN_PRODUCT] ?? 0],
        ];
    @endphp
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
        @foreach($chips as $val => $c)
            @php $active = (string)request('status') === (string)$val; @endphp
            <a href="{{ route('returns.index', $val !== '' ? ['status'=>$val] : []) }}"
               style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;padding:6px 12px;border-radius:999px;text-decoration:none;border:1px solid {{ $active ? 'var(--a-accent)' : 'var(--a-border)' }};background:{{ $active ? 'var(--a-accent)' : '#fff' }};color:{{ $active ? '#fff' : 'var(--a-text-2)' }}">
                {{ $c[0] }}
                <span style="font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;background:{{ $active ? 'rgba(255,255,255,.25)' : 'var(--a-surface-2)' }}">{{ $c[1] }}</span>
            </a>
        @endforeach
    </div>

    <div class="admin-card">
        <div class="admin-card-head">
            <x-admin.section-head icon="rotate" title="Returns workflow"/>
            <form method="GET">
                <select name="status" class="input" onchange="this.form.submit()" style="width:auto">
                    <option value="">All return states</option>
                    @foreach([
                        \App\Services\Orders\OrderStateMachine::RETURN_PENDING => 'Return Pending',
                        \App\Services\Orders\OrderStateMachine::RETURNED => 'Returned (awaiting inspection)',
                        \App\Services\Orders\OrderStateMachine::AWAITING_RETURN_PRODUCT => 'Awaiting Return Product',
                    ] as $k => $v)
                        <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if($orders->isEmpty())
            <x-admin.empty icon="rotate" title="No returns in flight"/>
        @else
            <div class="atable-wrap">
                <table class="atable">
                    <thead><tr><th>Order</th><th>Store</th><th>Customer</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach($orders as $o)
                        <tr>
                            <td><a href="{{ route('orders.show', $o) }}" style="color:var(--a-accent);font-weight:700;text-decoration:none">{{ $o->order_number }}</a></td>
                            <td><strong>{{ optional($o->store)->dfid }}</strong></td>
                            <td>{{ $o->customer_name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $o->customer_phone }}</div></td>
                            <td>{{ $o->return_reason ? ucwords(str_replace('_',' ',$o->return_reason)) : '—' }}</td>
                            <td><x-admin.pill :status="$o->status" :label="$o->statusLabel()"/></td>
                            <td style="text-align:right">
                                @if($o->status === \App\Services\Orders\OrderStateMachine::RETURNED)
                                    <button class="btn btn-dark btn-sm" onclick="document.getElementById('insp-{{ $o->id }}').showModal()">Inspect</button>
                                    <dialog id="insp-{{ $o->id }}" style="border:none;border-radius:12px;padding:0;max-width:520px;width:90%">
                                        <form method="POST" action="{{ route('returns.inspect', $o) }}" enctype="multipart/form-data" class="admin-card" style="margin:0">
                                            @csrf
                                            <div class="admin-card-head"><x-admin.section-head icon="check" :title="'Inspect '.$o->order_number"/></div>
                                            <div class="admin-card-body" style="display:flex;flex-direction:column;gap:8px">
                                                <label><span style="font-size:12px;font-weight:600">Decision</span>
                                                    <select class="input" name="decision" required>
                                                        <option value="restock">Good condition — restock</option>
                                                        <option value="damage">Damaged — write off</option>
                                                    </select>
                                                </label>
                                                <label><span style="font-size:12px;font-weight:600">Condition grade</span>
                                                    <select class="input" name="condition_grade">
                                                        @foreach(['A'=>'A · like new','B'=>'B · minor','C'=>'C · used','D'=>'D · damaged'] as $g=>$l)<option value="{{ $g }}">{{ $l }}</option>@endforeach
                                                    </select>
                                                </label>
                                                <label><span style="font-size:12px;font-weight:600">Responsible (if damaged)</span>
                                                    <select class="input" name="responsible_party"><option value="">—</option><option value="warehouse">Warehouse</option><option value="courier">Courier</option><option value="vendor">Vendor</option></select>
                                                </label>
                                                <label><span style="font-size:12px;font-weight:600">Damage reason</span><input class="input" name="damage_reason"></label>
                                                <label><span style="font-size:12px;font-weight:600">Damaged qty</span><input class="input" name="damaged_qty" type="number" min="1" value="1"></label>
                                                <label><span style="font-size:12px;font-weight:600">Notes</span><textarea class="input" name="inspection_notes" rows="2"></textarea></label>
                                                <label><span style="font-size:12px;font-weight:600">Photos</span><input type="file" name="photos[]" multiple accept="image/*"></label>
                                            </div>
                                            <div class="admin-card-body" style="border-top:1px solid var(--a-border);display:flex;justify-content:flex-end;gap:8px">
                                                <button class="btn btn-outline" type="button" onclick="this.closest('dialog').close()">Cancel</button>
                                                <button class="btn btn-dark">Save inspection</button>
                                            </div>
                                        </form>
                                    </dialog>
                                @elseif(in_array($o->status, [\App\Services\Orders\OrderStateMachine::RETURN_PENDING, \App\Services\Orders\OrderStateMachine::AWAITING_RETURN_PRODUCT], true))
                                    <div style="display:inline-flex;gap:4px">
                                        <form method="POST" action="{{ route('returns.receive', $o) }}" onsubmit="return confirm('Mark {{ $o->order_number }} as received at the warehouse?')">
                                            @csrf
                                            <button class="btn btn-dark btn-sm">Receive parcel</button>
                                        </form>
                                        <a href="{{ route('orders.show', $o) }}" class="btn btn-outline btn-sm">View</a>
                                    </div>
                                @else
                                    <a href="{{ route('orders.show', $o) }}" class="btn btn-outline btn-sm">View</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $orders->links() }}</div>
        @endif
    </div>
    <x-live-refresh scope="returns"/>
</x-app-layout>
