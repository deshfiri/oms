<x-app-layout>
    @section('title', 'Record damage')
    <div class="admin-page-header"><div><h1>Record damage</h1><p class="sub">Logged locally and posted to the storefront's /damages endpoint.</p></div></div>

    <form method="POST" action="{{ route('damages.store') }}" enctype="multipart/form-data" class="admin-card" style="max-width:560px">
        @csrf
        <div class="admin-card-body" style="display:flex;flex-direction:column;gap:10px">
            <label><span style="font-size:12px;font-weight:600">Store *</span>
                <select name="store_id" class="input" required>
                    <option value="">— select store —</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}">{{ ($s->dfid ? $s->dfid.' · ' : '').($s->business_name ?? $s->name) }}</option>
                    @endforeach
                </select>
            </label>
            <label><span style="font-size:12px;font-weight:600">SKU *</span><input name="sku" autofocus class="input" style="font-family:ui-monospace,monospace" required></label>
            <label><span style="font-size:12px;font-weight:600">Quantity *</span><input name="qty" type="number" min="1" value="1" class="input" required></label>
            <label><span style="font-size:12px;font-weight:600">Reason</span>
                <select name="reason" class="input" required>
                    <option>Dropped during picking</option>
                    <option>Found broken on intake</option>
                    <option>Customer return — damaged</option>
                    <option>Expired / spoiled</option>
                    <option>Other</option>
                </select>
            </label>
            <label><span style="font-size:12px;font-weight:600">Photos</span><input type="file" name="photos[]" multiple accept="image/*"></label>
            <button class="btn btn-dark btn-lg btn-block" style="margin-top:6px">Save & post to storefront</button>
        </div>
    </form>
</x-app-layout>
