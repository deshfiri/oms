<x-app-layout>
    @section('title', 'RMA #'.$rma->id)
    <div class="admin-page-header"><div><h1>RMA #{{ $rma->id }} — {{ optional($rma->order)->order_number }}</h1><p class="sub"><x-admin.pill :status="$rma->status"/></p></div></div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px">
        <div style="display:flex;flex-direction:column;gap:14px">
            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="clock" title="Lifecycle"/></div>
                <div class="admin-card-body">
                    <ol style="list-style:none;padding:0;margin:0;font-size:13px;display:flex;flex-direction:column;gap:6px">
                        <li>● Requested {{ optional($rma->opened_at)->diffForHumans() }}</li>
                        <li>{{ $rma->approved_at ? '●' : '○' }} Approved {{ optional($rma->approved_at)->diffForHumans() ?? '—' }}</li>
                        <li>{{ $rma->received_at ? '●' : '○' }} Received {{ optional($rma->received_at)->diffForHumans() ?? '—' }}</li>
                        <li>{{ $rma->decided_at  ? '●' : '○' }} Inspected {{ optional($rma->decided_at)->diffForHumans() ?? '—' }}</li>
                        <li>{{ $rma->completed_at ? '●' : '○' }} Completed {{ optional($rma->completed_at)->diffForHumans() ?? '—' }}</li>
                    </ol>
                </div>
            </div>

            @if($rma->order)
            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="cart" title="Items in order"/></div>
                <div class="atable-wrap">
                    <table class="atable">
                        <tbody>
                        @foreach($rma->order->items as $it)
                            <tr>
                                <td style="font-family:ui-monospace,monospace;font-size:12px">{{ $it->sku }}</td>
                                <td>{{ $it->name }}</td>
                                <td style="text-align:right">× {{ $it->qty }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            @if(in_array($rma->status, ['received','inspected']))
            <form method="POST" action="{{ route('returns.inspect', $rma) }}" enctype="multipart/form-data" class="admin-card">
                @csrf
                <div class="admin-card-head"><x-admin.section-head icon="check" title="Inspection"/></div>
                <div class="admin-card-body" style="display:flex;flex-direction:column;gap:10px">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <label><span style="font-size:12px;font-weight:600">Condition</span>
                            <select name="condition_grade" class="input">
                                @foreach(['A'=>'A — Like new','B'=>'B — Open box','C'=>'C — Used','D'=>'D — Damaged'] as $g=>$lbl)
                                    <option value="{{ $g }}" @selected($rma->condition_grade===$g)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label><span style="font-size:12px;font-weight:600">Decision</span>
                            <select name="decision" class="input">
                                @foreach(['restock','damage','exchange','refund_only'] as $d)
                                    <option value="{{ $d }}" @selected($rma->decision===$d)>{{ str_replace('_',' ',$d) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <textarea name="inspection_notes" rows="3" class="input" placeholder="Notes">{{ $rma->inspection_notes }}</textarea>
                    <input type="file" name="photos[]" multiple accept="image/*">
                    <button class="btn btn-dark">Save inspection</button>
                </div>
            </form>
            @endif

            @if(! empty($rma->photos_json))
                <div class="admin-card">
                    <div class="admin-card-head"><x-admin.section-head icon="package" title="Photos"/></div>
                    <div class="admin-card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px">
                        @foreach($rma->photos_json as $p)
                            <img src="{{ asset('storage/'.$p) }}" style="width:100%;height:120px;object-fit:cover;border-radius:var(--a-r)">
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div style="display:flex;flex-direction:column;gap:10px">
            @if($rma->status === 'requested')
                <form method="POST" action="{{ route('returns.approve', $rma) }}">@csrf<button class="btn btn-dark btn-block" >Approve</button></form>
                <form method="POST" action="{{ route('returns.reject', $rma) }}">@csrf<button class="btn btn-danger btn-block" >Reject</button></form>
            @endif
            @if($rma->status === 'approved')
                <form method="POST" action="{{ route('returns.receive', $rma) }}">@csrf<button class="btn btn-dark btn-block" >Mark received</button></form>
            @endif
            @if($rma->status === 'inspected')
                <form method="POST" action="{{ route('returns.complete', $rma) }}">@csrf<button class="btn btn-dark btn-block" >Complete RMA</button></form>
            @endif
        </div>
    </div>
</x-app-layout>
