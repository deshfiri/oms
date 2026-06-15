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

    @if($store->exists)

    {{-- ── New OTP flash (shown once right after generation) ─────────── --}}
    @if(session('new_otp'))
    <div class="admin-card" style="max-width:760px;margin-top:16px;border:2px solid #059669;background:#f0fdf4">
        <div class="admin-card-body">
            <p style="font-size:13px;font-weight:700;color:#065f46;margin-bottom:8px">
                ✓ OTP generated — copy it now. It will not be shown again.
            </p>
            <div style="display:flex;align-items:center;gap:10px">
                <code id="newOtpCode" style="flex:1;background:#fff;border:1px solid #6ee7b7;border-radius:8px;padding:10px 14px;font-size:14px;font-family:ui-monospace,monospace;letter-spacing:.04em;word-break:break-all">{{ session('new_otp') }}</code>
                <button type="button" onclick="copyOtp()" class="btn btn-outline btn-sm" style="flex-shrink:0">Copy</button>
            </div>
            <p style="font-size:12px;color:#047857;margin-top:8px;margin-bottom:0">
                এই OTP টি client store admin কে দিন। তারা storefront-এর OMS disconnect form-এ paste করবে।
            </p>
        </div>
    </div>
    @endif

    {{-- ── OTP codes ────────────────────────────────────────────────────── --}}
    <div class="admin-card" style="max-width:760px;margin-top:16px">
        <div class="admin-card-head" style="display:flex;align-items:center;justify-content:space-between">
            <x-admin.section-head icon="user" title="OTP Codes" description="Client-এর OMS connection disconnect করার জন্য one-time authorization code"/>
            <button type="button" onclick="document.getElementById('genOtpForm').style.display=document.getElementById('genOtpForm').style.display==='none'?'block':'none'" class="btn btn-dark btn-sm">Generate OTP</button>
        </div>

        {{-- Generate form (hidden by default) --}}
        <div id="genOtpForm" style="display:none;padding:14px 20px;border-bottom:1px solid var(--a-border);background:var(--a-surface-2)">
            <form method="POST" action="{{ route('stores.otp.store', $store) }}" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:14px">
                @csrf
                <input type="hidden" name="allowed_actions[]" value="revoke_api">
                <div>
                    <p style="font-size:12px;font-weight:600;margin-bottom:6px">Expires in (hours) <span style="font-weight:400;color:var(--a-text-3)">— optional</span></p>
                    <input type="number" name="expires_in_hours" min="1" max="720" placeholder="Never" class="input" style="width:120px">
                </div>
                <button type="submit" class="btn btn-dark btn-sm">Generate &amp; show OTP</button>
            </form>
        </div>

        {{-- OTP table --}}
        <div class="admin-card-body" style="padding:0">
            @php $otps = $store->otpCodes ?? collect(); @endphp
            @if($otps->isEmpty())
                <p style="padding:20px;font-size:13px;color:var(--a-text-3)">কোনো OTP নেই। <strong>Generate OTP</strong> বাটনে ক্লিক করুন।</p>
            @else
            <table style="width:100%;font-size:13px;border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid var(--a-border);text-align:left">
                        <th style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--a-text-2)">Status</th>
                        <th style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--a-text-2)">Expires</th>
                        <th style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--a-text-2)">Created</th>
                        <th style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--a-text-2)">Used at</th>
                        <th style="padding:10px 16px"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($otps as $otp)
                    @php
                        $statusColor = $otp->isUsed() ? '#6b7280' : ($otp->isExpired() ? '#dc2626' : '#059669');
                        $statusBg    = $otp->isUsed() ? '#f3f4f6' : ($otp->isExpired() ? '#fee2e2' : '#d1fae5');
                    @endphp
                    <tr style="border-bottom:1px solid var(--a-border)">
                        <td style="padding:10px 16px">
                            <span style="display:inline-block;background:{{ $statusBg }};color:{{ $statusColor }};padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600">
                                {{ $otp->statusLabel() }}
                            </span>
                        </td>
                        <td style="padding:10px 16px;color:var(--a-text-2)">
                            {{ $otp->expires_at ? $otp->expires_at->format('d M Y H:i') : '—' }}
                        </td>
                        <td style="padding:10px 16px;color:var(--a-text-2)">
                            {{ $otp->created_at->format('d M Y H:i') }}
                        </td>
                        <td style="padding:10px 16px;color:var(--a-text-2)">
                            {{ $otp->used_at ? $otp->used_at->format('d M Y H:i') : '—' }}
                        </td>
                        <td style="padding:10px 16px;text-align:right">
                            @if($otp->isValid())
                            <form method="POST" action="{{ route('stores.otp.destroy', [$store, $otp]) }}" style="display:inline" onsubmit="return confirm('এই OTP revoke করবেন?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline btn-sm" style="color:var(--a-danger,#dc2626)">Revoke</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    @endif

    @push('scripts')
    <script>
    function copyOtp() {
        const code = document.getElementById('newOtpCode')?.textContent?.trim();
        if (code) navigator.clipboard.writeText(code).then(() => {
            const btn = document.querySelector('[onclick="copyOtp()"]');
            if (btn) { btn.textContent = 'Copied!'; setTimeout(() => btn.textContent = 'Copy', 1500); }
        });
    }
    </script>
    @endpush
</x-app-layout>
