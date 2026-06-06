<x-app-layout>
    @section('title', 'Start stock count')
    <div class="admin-page-header"><div><h1>Start a stock count</h1></div></div>
    <form method="POST" action="{{ route('stock-counts.store') }}" class="admin-card" style="max-width:480px">
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
            <label><span style="font-size:12px;font-weight:600">Location</span><input name="location" placeholder="A1, Main warehouse, etc." class="input"></label>
            <button class="btn btn-dark">Open count</button>
        </div>
    </form>
</x-app-layout>
