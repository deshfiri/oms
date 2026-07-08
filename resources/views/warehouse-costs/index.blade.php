<x-app-layout>
    @section('title', 'Warehouse Product Costs')

    @php
        $totalValue   = (float) ($summary->total_stock_value  ?? 0);
        $dailyCost    = (float) ($summary->total_daily_cost   ?? 0);
        $monthlyCost  = $dailyCost * 30;
        $withCost     = (int)   ($summary->products_with_cost ?? 0);
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>Warehouse Product Costs</h1>
            <p class="sub">Set cost price &amp; daily storage rate per SKU. Totals are auto-calculated live as you type.</p>
        </div>
    </div>

    {{-- ── Summary strip ────────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:14px">
        <div class="admin-card">
            <div class="admin-card-body" style="padding:14px 16px">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--a-text-3);margin-bottom:4px">Products with cost data</div>
                <div id="sum-count" style="font-size:22px;font-weight:700">{{ $withCost }}</div>
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-body" style="padding:14px 16px">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--a-text-3);margin-bottom:4px">Total Stock Value</div>
                <div id="sum-value" style="font-size:22px;font-weight:700">৳{{ number_format($totalValue, 2) }}</div>
                <div style="font-size:11px;color:var(--a-text-3)">capital tied up in warehouse</div>
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-body" style="padding:14px 16px">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--a-text-3);margin-bottom:4px">Daily Holding Cost</div>
                <div id="sum-daily" style="font-size:22px;font-weight:700">৳{{ number_format($dailyCost, 2) }}</div>
                <div style="font-size:11px;color:var(--a-text-3)">per day across all stock</div>
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-body" style="padding:14px 16px">
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--a-text-3);margin-bottom:4px">Monthly Holding Cost</div>
                <div id="sum-monthly" style="font-size:22px;font-weight:700">৳{{ number_format($monthlyCost, 2) }}</div>
                <div style="font-size:11px;color:var(--a-text-3)">daily × 30 estimate</div>
            </div>
        </div>
    </div>

    {{-- ── Filter bar ───────────────────────────────────────────── --}}
    <form method="GET" class="admin-card" style="margin-bottom:14px">
        <div class="admin-card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <select name="store_id" class="input" style="width:auto" onchange="this.form.submit()">
                <option value="">All stores</option>
                @foreach($stores as $s)
                    <option value="{{ $s->id }}" @selected(request('store_id') == $s->id)>
                        {{ ($s->dfid ? $s->dfid.' · ' : '') . ($s->business_name ?? $s->name) }}
                    </option>
                @endforeach
            </select>
            <input name="q" value="{{ request('q') }}" placeholder="SKU or product name" class="input" style="width:260px">
            <button class="btn btn-dark">Filter</button>
            @if(request('q') || request('store_id'))
                <a href="{{ route('warehouse-costs.index') }}" class="btn btn-outline">Clear</a>
            @endif
        </div>
    </form>

    {{-- ── Product cost table ───────────────────────────────────── --}}
    <form method="POST" action="{{ route('warehouse-costs.save') }}" id="costs-form">
        @csrf
        <div class="admin-card">
            <div class="admin-card-head">
                <x-admin.section-head icon="inventory" title="Product cost settings"/>
                <button type="submit" class="btn btn-dark">Save all on this page</button>
            </div>

            @if($products->isEmpty())
                <x-admin.empty icon="box" title="No products found" description="Sync products from the Stores page, or adjust your filter."/>
            @else
                <div class="atable-wrap">
                    <table class="atable" id="costs-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>SKU</th>
                                <th>Product</th>
                                <th>Store</th>
                                <th style="text-align:right">In Stock</th>
                                <th style="text-align:right;min-width:120px">Cost Price (৳) *</th>
                                <th style="text-align:right;min-width:130px">Daily Rate (৳/unit)</th>
                                <th style="text-align:right">Stock Value</th>
                                <th style="text-align:right">Daily Cost</th>
                                <th style="text-align:right">Monthly Cost</th>
                                <th style="min-width:140px">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($products as $i => $p)
                            @php
                                $key      = $p->store_id . '_' . $p->sku;
                                $saved    = $costsMap[$key] ?? null;
                                $stock    = (int) ($stockMap[$key]->total_available ?? 0);
                                $costPx   = (float) ($saved->cost_price ?? 0);
                                $dailyRate = (float) ($saved->daily_storage_rate ?? 0);
                                $rowIdx   = ($products->currentPage() - 1) * $products->perPage() + $i;
                            @endphp
                            <tr data-stock="{{ $stock }}">
                                <td style="color:var(--a-text-3);font-size:12px">{{ $loop->iteration }}</td>
                                <td style="font-family:ui-monospace,monospace;font-size:12px">
                                    {{ $p->sku }}
                                    <input type="hidden" name="costs[{{ $rowIdx }}][sku]"      value="{{ $p->sku }}">
                                    <input type="hidden" name="costs[{{ $rowIdx }}][store_id]" value="{{ $p->store_id }}">
                                </td>
                                <td>
                                    <div style="font-size:13px">{{ $p->name }}</div>
                                    @if($p->bin_location)
                                        <div style="font-size:11px;color:var(--a-text-3)">Bin: {{ $p->bin_location }}</div>
                                    @endif
                                </td>
                                <td style="font-size:12px">
                                    <strong>{{ optional($p->store)->dfid ?? '—' }}</strong>
                                    <div style="font-size:11px;color:var(--a-text-3)">{{ optional($p->store)->business_name ?? '' }}</div>
                                </td>
                                <td style="text-align:right;font-weight:700;font-size:14px">
                                    {{ $stock }}
                                    <div style="font-size:10px;color:var(--a-text-3);font-weight:400">units</div>
                                </td>
                                <td style="text-align:right">
                                    <input type="number"
                                           name="costs[{{ $rowIdx }}][cost_price]"
                                           value="{{ number_format($costPx, 2, '.', '') }}"
                                           step="0.01" min="0"
                                           class="input calc-cost"
                                           style="text-align:right;padding:6px 8px;width:110px">
                                </td>
                                <td style="text-align:right">
                                    <input type="number"
                                           name="costs[{{ $rowIdx }}][daily_storage_rate]"
                                           value="{{ number_format($dailyRate, 4, '.', '') }}"
                                           step="0.0001" min="0"
                                           class="input calc-rate"
                                           style="text-align:right;padding:6px 8px;width:110px">
                                </td>
                                <td class="calc-value" style="text-align:right;font-weight:600">
                                    ৳{{ number_format($stock * $costPx, 2) }}
                                </td>
                                <td class="calc-daily" style="text-align:right">
                                    ৳{{ number_format($stock * $dailyRate, 2) }}
                                </td>
                                <td class="calc-monthly" style="text-align:right;color:var(--a-text-2)">
                                    ৳{{ number_format($stock * $dailyRate * 30, 2) }}
                                </td>
                                <td>
                                    <input type="text"
                                           name="costs[{{ $rowIdx }}][notes]"
                                           value="{{ $saved->notes ?? '' }}"
                                           placeholder="optional"
                                           class="input"
                                           style="padding:6px 8px;font-size:12px;width:130px">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background:var(--a-surface-2);font-weight:700">
                                <td colspan="7" style="text-align:right;font-size:12px;color:var(--a-text-2)">Page totals →</td>
                                <td id="foot-value"   style="text-align:right">৳0.00</td>
                                <td id="foot-daily"   style="text-align:right">৳0.00</td>
                                <td id="foot-monthly" style="text-align:right">৳0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="admin-pagination" style="display:flex;justify-content:space-between;align-items:center">
                    {{ $products->links() }}
                    <button type="submit" class="btn btn-dark" style="flex-shrink:0">Save all on this page</button>
                </div>
            @endif
        </div>
    </form>

    <div class="admin-card" style="margin-top:14px">
        <div class="admin-card-body" style="font-size:12px;color:var(--a-text-2);display:flex;gap:24px;flex-wrap:wrap">
            <span><strong>Cost Price</strong> — what you paid per unit (purchase cost). Used to value total stock.</span>
            <span><strong>Daily Storage Rate</strong> — warehouse holding cost per unit per day (e.g. rent ÷ capacity). Used to track daily/monthly overhead.</span>
            <span><strong>Stock Value</strong> = In Stock × Cost Price.</span>
            <span><strong>Daily / Monthly Cost</strong> = In Stock × Daily Rate × (1 or 30).</span>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const taka = n => '৳' + n.toFixed(2);

        function recalcAll() {
            var totalValue = 0, totalDaily = 0;

            document.querySelectorAll('#costs-table tbody tr').forEach(function (row) {
                var stock     = parseFloat(row.dataset.stock)                           || 0;
                var costPx    = parseFloat(row.querySelector('.calc-cost').value)       || 0;
                var dailyRate = parseFloat(row.querySelector('.calc-rate').value)       || 0;

                var value   = stock * costPx;
                var daily   = stock * dailyRate;
                var monthly = daily * 30;

                row.querySelector('.calc-value').textContent   = taka(value);
                row.querySelector('.calc-daily').textContent   = taka(daily);
                row.querySelector('.calc-monthly').textContent = taka(monthly);

                totalValue += value;
                totalDaily += daily;
            });

            document.getElementById('foot-value').textContent   = taka(totalValue);
            document.getElementById('foot-daily').textContent   = taka(totalDaily);
            document.getElementById('foot-monthly').textContent = taka(totalDaily * 30);
        }

        document.getElementById('costs-table')?.addEventListener('input', function (e) {
            if (e.target.classList.contains('calc-cost') || e.target.classList.contains('calc-rate')) {
                recalcAll();
            }
        });

        recalcAll(); // initialize page totals from server-rendered values
    })();
    </script>
    @endpush
</x-app-layout>
