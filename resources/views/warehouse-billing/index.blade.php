<x-app-layout>
    @section('title', 'Warehouse Billing')

    @php
        $grandStorageCharge  = $billing->sum('storage_charge');
        $grandPackagingCharge= $billing->sum('packaging_charge');
        $grandTotal          = $billing->sum('total_charge');
        $grandItemsStored    = $billing->sum('items_stored');
        $grandItemsPacked    = $billing->sum('items_packed');
        $monthLabel          = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>Warehouse Billing</h1>
            <p class="sub">Per-store storage &amp; packaging charges — {{ $monthLabel }}</p>
        </div>
        {{-- Month selector --}}
        <form method="GET" style="display:flex;gap:8px;align-items:center">
            <select name="month" class="input" style="width:auto" onchange="this.form.submit()">
                @foreach($monthOptions as $m)
                    <option value="{{ $m }}" @selected($m === $month)>
                        {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $m)->format('M Y') }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- ── Summary stat strip ───────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:14px">
        <div class="admin-card">
            <div class="admin-card-body" style="padding:14px 16px">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--a-text-3);margin-bottom:4px">Stores billed</div>
                <div style="font-size:24px;font-weight:700">{{ $billing->count() }}</div>
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-body" style="padding:14px 16px">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--a-text-3);margin-bottom:4px">Items in warehouse</div>
                <div style="font-size:24px;font-weight:700">{{ number_format($grandItemsStored) }}</div>
                <div style="font-size:11px;color:var(--a-text-3)">units stored</div>
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-body" style="padding:14px 16px">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--a-text-3);margin-bottom:4px">Storage charges</div>
                <div style="font-size:24px;font-weight:700">৳{{ number_format($grandStorageCharge, 2) }}</div>
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-body" style="padding:14px 16px">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--a-text-3);margin-bottom:4px">Packaging charges</div>
                <div style="font-size:24px;font-weight:700">৳{{ number_format($grandPackagingCharge, 2) }}</div>
                <div style="font-size:11px;color:var(--a-text-3)">{{ number_format($grandItemsPacked) }} items packed</div>
            </div>
        </div>
        <div class="admin-card" style="border:2px solid var(--a-accent)">
            <div class="admin-card-body" style="padding:14px 16px">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--a-accent);margin-bottom:4px">Grand total due</div>
                <div style="font-size:24px;font-weight:800;color:var(--a-accent)">৳{{ number_format($grandTotal, 2) }}</div>
                <div style="font-size:11px;color:var(--a-text-3)">{{ $monthLabel }}</div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:14px;align-items:start">

        {{-- ── LEFT: Per-store billing table ────────────────────────── --}}
        <div class="admin-card">
            <div class="admin-card-head">
                <x-admin.section-head icon="store" title="Store billing breakdown — {{ $monthLabel }}"/>
            </div>
            @if($billing->isEmpty())
                <x-admin.empty icon="store" title="No active stores" description="Add a store first."/>
            @else
                <div class="atable-wrap">
                    <table class="atable">
                        <thead>
                            <tr>
                                <th>Store</th>
                                <th style="text-align:right">Items stored</th>
                                <th style="text-align:right">Storage rate</th>
                                <th style="text-align:right">Storage charge</th>
                                <th style="text-align:right">Items packed</th>
                                <th style="text-align:right">Pkg rate</th>
                                <th style="text-align:right">Pkg charge</th>
                                <th style="text-align:right;font-weight:700">Total due</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($billing as $row)
                            <tr>
                                <td>
                                    <div style="font-weight:600">{{ $row['store']->business_name ?? $row['store']->name }}</div>
                                    <div style="font-size:11px;color:var(--a-text-3);font-family:ui-monospace,monospace">{{ $row['store']->dfid ?? '—' }}</div>
                                    @if($row['has_rate_override'])
                                        <span style="font-size:10px;background:rgba(234,179,8,.15);color:#92400e;padding:1px 6px;border-radius:999px;font-weight:600">custom rate</span>
                                    @endif
                                </td>
                                <td style="text-align:right;font-weight:600">{{ number_format($row['items_stored']) }}</td>
                                <td style="text-align:right;color:var(--a-text-2);font-size:12px">৳{{ number_format($row['storage_rate'], 2) }}/item</td>
                                <td style="text-align:right">৳{{ number_format($row['storage_charge'], 2) }}</td>
                                <td style="text-align:right">
                                    {{ number_format($row['items_packed']) }}
                                    <div style="font-size:11px;color:var(--a-text-3)">{{ $row['orders_packed'] }} order(s)</div>
                                </td>
                                <td style="text-align:right;color:var(--a-text-2);font-size:12px">৳{{ number_format($row['packaging_rate'], 2) }}/item</td>
                                <td style="text-align:right">৳{{ number_format($row['packaging_charge'], 2) }}</td>
                                <td style="text-align:right;font-weight:800;font-size:15px;color:var(--a-accent)">
                                    ৳{{ number_format($row['total_charge'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background:var(--a-surface-2);font-weight:700">
                                <td>Total</td>
                                <td style="text-align:right">{{ number_format($grandItemsStored) }}</td>
                                <td></td>
                                <td style="text-align:right">৳{{ number_format($grandStorageCharge, 2) }}</td>
                                <td style="text-align:right">{{ number_format($grandItemsPacked) }}</td>
                                <td></td>
                                <td style="text-align:right">৳{{ number_format($grandPackagingCharge, 2) }}</td>
                                <td style="text-align:right;font-size:16px;color:var(--a-accent)">৳{{ number_format($grandTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

        {{-- ── RIGHT: Rate settings ──────────────────────────────────── --}}
        <div style="display:flex;flex-direction:column;gap:14px">

            {{-- Global rate --}}
            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="settings" title="Global rates"/></div>
                <div class="admin-card-body">
                    <p style="font-size:12px;color:var(--a-text-2);margin:0 0 10px">
                        Applied to every store unless a per-store override is set below.
                    </p>
                    <form method="POST" action="{{ route('warehouse-billing.rates.global') }}" style="display:flex;flex-direction:column;gap:8px">
                        @csrf
                        <label>
                            <span style="font-size:11px;font-weight:600;color:var(--a-text-2);display:block;margin-bottom:3px">Storage rate (৳ / item / month)</span>
                            <input name="storage_rate_per_item_per_month"
                                   type="number" step="0.01" min="0"
                                   value="{{ number_format((float)$globalRate->storage_rate_per_item_per_month, 2, '.', '') }}"
                                   class="input" style="text-align:right">
                        </label>
                        <label>
                            <span style="font-size:11px;font-weight:600;color:var(--a-text-2);display:block;margin-bottom:3px">Packaging cost (৳ / item packed)</span>
                            <input name="packaging_cost_per_item"
                                   type="number" step="0.01" min="0"
                                   value="{{ number_format((float)$globalRate->packaging_cost_per_item, 2, '.', '') }}"
                                   class="input" style="text-align:right">
                        </label>
                        <button class="btn btn-dark btn-block" style="margin-top:4px">Save global rates</button>
                    </form>
                </div>
            </div>

            {{-- Per-store overrides --}}
            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="store" title="Per-store overrides"/></div>
                <div class="admin-card-body" style="display:flex;flex-direction:column;gap:12px">
                    <p style="font-size:12px;color:var(--a-text-2);margin:0">
                        Set both fields to 0 to remove the override and revert to global rates.
                    </p>
                    @foreach($stores as $s)
                        @php $ov = $storeRates->get($s->id); @endphp
                        <form method="POST" action="{{ route('warehouse-billing.rates.store', $s) }}"
                              style="border:1px solid var(--a-border);border-radius:6px;padding:10px">
                            @csrf
                            <div style="font-size:13px;font-weight:600;margin-bottom:8px">
                                {{ $s->business_name ?? $s->name }}
                                @if($ov)
                                    <span style="font-size:10px;background:rgba(234,179,8,.15);color:#92400e;padding:1px 6px;border-radius:999px;margin-left:4px">override active</span>
                                @endif
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
                                <label>
                                    <span style="font-size:10px;color:var(--a-text-3)">Storage ৳/item/mo</span>
                                    <input name="storage_rate_per_item_per_month"
                                           type="number" step="0.01" min="0"
                                           value="{{ number_format((float)($ov->storage_rate_per_item_per_month ?? 0), 2, '.', '') }}"
                                           class="input" style="text-align:right;padding:5px 8px;font-size:12px">
                                </label>
                                <label>
                                    <span style="font-size:10px;color:var(--a-text-3)">Pkg ৳/item</span>
                                    <input name="packaging_cost_per_item"
                                           type="number" step="0.01" min="0"
                                           value="{{ number_format((float)($ov->packaging_cost_per_item ?? 0), 2, '.', '') }}"
                                           class="input" style="text-align:right;padding:5px 8px;font-size:12px">
                                </label>
                            </div>
                            <button class="btn btn-outline btn-block" style="font-size:12px;padding:5px">
                                {{ $ov ? 'Update override' : 'Set override' }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
