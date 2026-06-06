<x-app-layout>
    @section('title', 'Record lost')
    <div class="admin-page-header"><div><h1>Record a lost product</h1></div></div>
    <form method="POST" action="{{ route('lost.store') }}" class="admin-card" style="max-width:640px">
        @csrf
        <div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <label style="grid-column:1/-1"><span style="font-size:12px;font-weight:600">Store *</span>
                <select class="input" name="store_id" required><option value="">— select —</option>
                    @foreach($stores as $s)<option value="{{ $s->id }}">{{ ($s->dfid?$s->dfid.' · ':'').($s->business_name ?? $s->name) }}</option>@endforeach
                </select>
            </label>
            <label><span style="font-size:12px;font-weight:600">Order number (optional)</span><input class="input" name="order_id" type="number" placeholder="OMS order ID if applicable"></label>
            <label><span style="font-size:12px;font-weight:600">SKU (optional)</span><input class="input" name="sku" style="font-family:ui-monospace,monospace"></label>
            <label><span style="font-size:12px;font-weight:600">Qty *</span><input class="input" name="qty" type="number" min="1" value="1" required></label>
            <label><span style="font-size:12px;font-weight:600">Responsible party *</span>
                <select class="input" name="responsible_party" required>
                    @foreach(\App\Models\LostLog::PARTIES as $p)<option value="{{ $p }}">{{ ucfirst($p) }}</option>@endforeach
                </select>
            </label>
            <label><span style="font-size:12px;font-weight:600">Reason</span><input class="input" name="reason"></label>
            <label><span style="font-size:12px;font-weight:600">Compensation amount (৳)</span><input class="input" name="compensation_amount" type="number" step="0.01" min="0" value="0"></label>
            <label><span style="font-size:12px;font-weight:600">Compensation status</span>
                <select class="input" name="compensation_status"><option value="pending">Pending</option><option value="paid">Paid</option><option value="waived">Waived</option></select>
            </label>
        </div>
        <div class="admin-card-body" style="border-top:1px solid var(--a-border);display:flex;justify-content:flex-end;gap:8px">
            <a href="{{ route('lost.index') }}" class="btn btn-outline">Cancel</a>
            <button class="btn btn-dark">Save</button>
        </div>
    </form>
</x-app-layout>
