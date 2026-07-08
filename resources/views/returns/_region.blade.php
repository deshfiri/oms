@php $S = \App\Services\Orders\OrderStateMachine::class; @endphp

{{-- Status chips --}}
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
    @php
        $chips = [
            '' => ['All', $counts->sum()],
            $S::RETURN_PENDING => ['Return Pending', $counts[$S::RETURN_PENDING] ?? 0],
            $S::RETURNED => ['Returned · to inspect', $counts[$S::RETURNED] ?? 0],
            $S::AWAITING_RETURN_PRODUCT => ['Awaiting Product', $counts[$S::AWAITING_RETURN_PRODUCT] ?? 0],
        ];
    @endphp
    @foreach($chips as $val => $c)
        @php $active = (string)request('status') === (string)$val; @endphp
        <a href="{{ route('returns.index', $val !== '' ? ['status'=>$val] : []) }}"
           style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;padding:6px 12px;border-radius:999px;text-decoration:none;border:1px solid {{ $active ? 'var(--a-accent)' : 'var(--a-border)' }};background:{{ $active ? 'var(--a-accent)' : '#fff' }};color:{{ $active ? '#fff' : 'var(--a-text-2)' }}">
            {{ $c[0] }}
            <span style="font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;background:{{ $active ? 'rgba(255,255,255,.25)' : 'var(--a-surface-2)' }}">{{ $c[1] }}</span>
        </a>
    @endforeach
</div>

{{-- Returns table --}}
<div class="admin-card">
    <div class="admin-card-head">
        <x-admin.section-head icon="rotate" title="Returns workflow" :description="$orders->total().' result(s)'"/>
        <form method="GET" action="{{ route('returns.index') }}">
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
                                <dialog id="insp-{{ $o->id }}" style="border:none;border-radius:12px;padding:0;max-width:560px;width:90%">
                                    <form method="POST" action="{{ route('returns.inspect', $o) }}" enctype="multipart/form-data" class="admin-card" style="margin:0" id="inspform-{{ $o->id }}">
                                        @csrf
                                        <div class="admin-card-head"><x-admin.section-head icon="check" :title="'Inspect '.$o->order_number"/></div>
                                        <div class="admin-card-body" style="display:flex;flex-direction:column;gap:8px">
                                            {{-- Decision --}}
                                            <label><span style="font-size:12px;font-weight:600">Decision</span>
                                                <select class="input" name="decision" required
                                                    onchange="inspToggle(this,'{{ $o->id }}')">
                                                    <option value="restock">Good condition — restock</option>
                                                    <option value="damage">Damaged — write off</option>
                                                    <option value="refund_only">Refund only — item not resalable</option>
                                                    <option value="lost">Lost — item missing from parcel</option>
                                                </select>
                                            </label>
                                            {{-- Condition grade (restock / damage) --}}
                                            <div class="insp-section insp-restock insp-damage" data-insp="{{ $o->id }}">
                                                <label><span style="font-size:12px;font-weight:600">Condition grade</span>
                                                    <select class="input" name="condition_grade">
                                                        @foreach(['A'=>'A · like new','B'=>'B · minor','C'=>'C · used','D'=>'D · damaged'] as $g=>$l)<option value="{{ $g }}">{{ $l }}</option>@endforeach
                                                    </select>
                                                </label>
                                            </div>
                                            {{-- Damage fields (damage / lost) --}}
                                            <div class="insp-section insp-damage insp-lost" data-insp="{{ $o->id }}" style="display:none">
                                                <label><span style="font-size:12px;font-weight:600">Responsible party</span>
                                                    <select class="input" name="responsible_party"><option value="">—</option><option value="warehouse">Warehouse</option><option value="courier">Courier</option><option value="vendor">Vendor</option></select>
                                                </label>
                                                <label style="margin-top:6px"><span style="font-size:12px;font-weight:600">Damage / loss reason</span><input class="input" name="damage_reason"></label>
                                                <label style="margin-top:6px"><span style="font-size:12px;font-weight:600">Damaged qty</span><input class="input" name="damaged_qty" type="number" min="1" value="1"></label>
                                            </div>
                                            {{-- Refund fields (refund_only) --}}
                                            <div class="insp-section insp-refund_only" data-insp="{{ $o->id }}" style="display:none">
                                                <label><span style="font-size:12px;font-weight:600">Refund amount (৳)</span>
                                                    <input class="input" name="refund_amount" type="number" step="0.01" min="0.01"
                                                           value="{{ number_format((float)$o->grand_total, 2, '.', '') }}"
                                                           style="text-align:right">
                                                </label>
                                                <label style="margin-top:6px"><span style="font-size:12px;font-weight:600">Refund note</span><input class="input" name="refund_note"></label>
                                            </div>
                                            {{-- Notes + photos (all decisions) --}}
                                            <label><span style="font-size:12px;font-weight:600">Inspection notes</span><textarea class="input" name="inspection_notes" rows="2"></textarea></label>
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
<script>
function inspToggle(sel, id) {
    var decision = sel.value;
    var all = document.querySelectorAll('[data-insp="' + id + '"]');
    all.forEach(function(el) { el.style.display = 'none'; });
    var show = document.querySelectorAll('.insp-' + decision + '[data-insp="' + id + '"]');
    show.forEach(function(el) { el.style.display = ''; });
}
// Init on page load — show grade section for default "restock"
document.querySelectorAll('[data-insp]').forEach(function(el) {
    if (el.classList.contains('insp-restock')) el.style.display = '';
});
</script>
