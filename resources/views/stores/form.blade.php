<x-app-layout>
    @section('title', $store->exists ? 'Edit store' : 'Add store')
    <div class="admin-page-header"><div><h1>{{ $store->exists ? 'Edit store' : 'Add store' }}</h1><p class="sub">Capture the merchant profile, then the DFCOMMERCE API credentials.</p></div></div>

    <form method="POST" action="{{ $store->exists ? route('stores.update', $store) : route('stores.store') }}" class="admin-card" style="max-width:760px">
        @csrf
        @if($store->exists) @method('PATCH') @endif

        <div class="admin-card-head"><x-admin.section-head icon="user" title="Merchant profile" description="Who runs this store"/></div>
        <div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <label style="grid-column:1/-1"><span style="font-size:12px;font-weight:600">DFID *</span>
                <input name="dfid" value="{{ old('dfid', $store->dfid) }}" required class="input" style="font-family:ui-monospace,monospace" placeholder="e.g. DF1024">
                @error('dfid') <p style="color:var(--a-danger,#dc2626);font-size:11px;margin-top:4px">{{ $message }}</p> @enderror
            </label>
            <label><span style="font-size:12px;font-weight:600">Business name *</span>
                <input name="business_name" value="{{ old('business_name', $store->business_name) }}" required class="input">
            </label>
            <label><span style="font-size:12px;font-weight:600">Domain name</span>
                <input name="domain_name" value="{{ old('domain_name', $store->domain_name) }}" class="input" placeholder="shop.example.com">
            </label>
            <label><span style="font-size:12px;font-weight:600">Customer name *</span>
                <input name="customer_name" value="{{ old('customer_name', $store->customer_name) }}" required class="input">
            </label>
            <label><span style="font-size:12px;font-weight:600">Customer phone *</span>
                <input name="customer_phone" value="{{ old('customer_phone', $store->customer_phone) }}" required class="input">
            </label>
        </div>

        <div class="admin-card-head" style="border-top:1px solid var(--a-border)"><x-admin.section-head icon="settings" title="API credentials" description="From DFCOMMERCE Admin → API Clients"/></div>
        <div class="admin-card-body" style="display:flex;flex-direction:column;gap:10px">
            <label><span style="font-size:12px;font-weight:600">Base URL *</span><input name="base_url" value="{{ old('base_url', $store->base_url) }}" placeholder="https://shop.example.com" class="input" required></label>
            <label><span style="font-size:12px;font-weight:600">API key *</span><input name="api_key" value="{{ old('api_key', $store->api_key) }}" class="input" style="font-family:ui-monospace,monospace" required></label>
            <label><span style="font-size:12px;font-weight:600">API secret {{ $store->exists ? '(blank = keep current)' : '*' }}</span><input name="api_secret" type="password" class="input" style="font-family:ui-monospace,monospace" @if(!$store->exists) required @endif></label>
            <label><span style="font-size:12px;font-weight:600">Webhook secret</span><input name="webhook_secret" value="{{ old('webhook_secret', $store->webhook_secret) }}" placeholder="Auto-generated if blank" class="input" style="font-family:ui-monospace,monospace"></label>
            @if($store->exists)
                <p style="font-size:12px;color:var(--a-text-2);margin:0">Webhook target: <code>{{ route('webhooks.dfcommerce', $store) }}</code></p>
            @endif
            <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $store->is_active ?? true))> Active</label>
            <div style="display:flex;gap:8px;margin-top:6px"><button class="btn btn-dark">Save</button><a href="{{ route('stores.index') }}" class="btn btn-outline">Cancel</a></div>
        </div>

        @if($store->exists)
        <div class="admin-card-head" style="border-top:1px solid var(--a-border)"><x-admin.section-head icon="truck" title="Courier configuration" description="Read-only — configured in the storefront admin, not here"/></div>
        <div class="admin-card-body">
            <p style="font-size:13px;color:var(--a-text-2);margin:0">
                The courier used for dispatch is controlled by the storefront's settings
                (<code>settings.group = 'couriers'</code>, <code>settings.key = 'default_courier'</code>).
                OMS reads this value automatically when an order is dispatched — no courier selection is needed here.
                If dispatch is using the wrong courier, update the setting in the storefront admin panel.
            </p>
        </div>
        @endif
    </form>
</x-app-layout>
