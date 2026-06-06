<x-app-layout>
    @section('title', 'Verify '.$order->order_number)
    <div class="admin-page-header">
        <div><h1>Verify · {{ $order->order_number }}</h1><p class="sub">{{ $order->store->dfid }} · {{ $order->store->business_name }} · placed {{ optional($order->placed_at)->diffForHumans() }}</p></div>
        <a href="{{ route('verification.index') }}" class="btn btn-outline">← Back</a>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px">
        <div style="display:flex;flex-direction:column;gap:14px">

            {{-- ───────── Customer & Delivery (DFCOMMERCE-style card with Edit toggle) ───────── --}}
            <div class="admin-card" x-data="{ edit: false }">
                <div class="admin-card-head" style="display:flex;justify-content:space-between;align-items:center">
                    <x-admin.section-head icon="user" title="Customer & delivery" description="Same address structure as the DFCOMMERCE storefront."/>
                    <button type="button" class="btn btn-outline btn-sm" x-show="!edit" @click="edit = true">Edit</button>
                    <button type="button" class="btn btn-ghost btn-sm"   x-show="edit"  @click="edit = false">Cancel</button>
                </div>

                {{-- READ-ONLY view (DFCOMMERCE storefront format) --}}
                <div class="admin-card-body" x-show="!edit" style="display:grid;grid-template-columns:1fr 1fr;gap:18px;font-size:13.5px;line-height:1.55">
                    <div>
                        <p style="font-size:10.5px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--a-text-3);margin:0 0 4px">Billed to</p>
                        <p style="margin:0;font-weight:700">{{ $order->customer_name ?: '—' }}</p>
                        <p style="margin:2px 0;color:var(--a-text-2);font-family:ui-monospace,monospace">{{ $order->customer_phone }}</p>
                        @if($order->customer_email)<p style="margin:0;color:var(--a-text-2)">{{ $order->customer_email }}</p>@endif
                    </div>
                    <div>
                        <p style="font-size:10.5px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--a-text-3);margin:0 0 4px">Ship to</p>
                        <p style="margin:0;font-weight:700">{{ $order->shipping_name ?: '—' }}</p>
                        <p style="margin:2px 0;color:var(--a-text-2);font-family:ui-monospace,monospace">{{ $order->shipping_phone }}</p>
                        <p style="margin:6px 0 0">{{ $order->shipping_address_line }}</p>
                        <p style="margin:0">{{ collect([$order->shipping_area, $order->shipping_city, $order->shipping_district])->filter()->implode(', ') }}{{ $order->shipping_postcode ? ' '.$order->shipping_postcode : '' }}</p>
                        <p style="margin:6px 0 0;color:var(--a-text-2);font-size:12px">Zone: <strong style="text-transform:capitalize">{{ str_replace('_',' ', $order->shipping_zone) }}</strong></p>
                    </div>
                </div>

                {{-- EDIT mode (only renders when Edit clicked) --}}
                <form method="POST" action="{{ route('verification.update', $order) }}" x-show="edit" x-cloak>
                    @csrf @method('PATCH')
                    <div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <label><span style="font-size:12px;font-weight:600">Customer name</span><input class="input" name="customer_name" value="{{ $order->customer_name }}"></label>
                        <label><span style="font-size:12px;font-weight:600">Customer phone</span><input class="input" name="customer_phone" value="{{ $order->customer_phone }}"></label>
                        <label><span style="font-size:12px;font-weight:600">Recipient name</span><input class="input" name="shipping_name" value="{{ $order->shipping_name }}"></label>
                        <label><span style="font-size:12px;font-weight:600">Recipient phone</span><input class="input" name="shipping_phone" value="{{ $order->shipping_phone }}"></label>
                        <label style="grid-column:1/-1"><span style="font-size:12px;font-weight:600">Address line</span><input class="input" name="shipping_address_line" value="{{ $order->shipping_address_line }}"></label>
                        <label><span style="font-size:12px;font-weight:600">Area</span><input class="input" name="shipping_area" value="{{ $order->shipping_area }}"></label>
                        <label><span style="font-size:12px;font-weight:600">City</span><input class="input" name="shipping_city" value="{{ $order->shipping_city }}"></label>
                        <label><span style="font-size:12px;font-weight:600">District</span><input class="input" name="shipping_district" value="{{ $order->shipping_district }}"></label>
                        <label><span style="font-size:12px;font-weight:600">Postcode</span><input class="input" name="shipping_postcode" value="{{ $order->shipping_postcode }}"></label>
                        <label style="grid-column:1/-1"><span style="font-size:12px;font-weight:600">Zone</span>
                            <select class="input" name="shipping_zone">
                                @foreach(['inside_dhaka'=>'Inside Dhaka','outside_dhaka'=>'Outside Dhaka','sub_city'=>'Sub-city'] as $k=>$v)
                                    <option value="{{ $k }}" @selected($order->shipping_zone===$k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="admin-card-body" style="border-top:1px solid var(--a-border);display:flex;justify-content:flex-end;gap:8px">
                        <button type="button" class="btn btn-ghost" @click="edit = false">Cancel</button>
                        <button class="btn btn-dark">Save changes</button>
                    </div>
                </form>
            </div>

            {{-- ───────── Discount / Coupon (small, separate from shipping) ───────── --}}
            <form method="POST" action="{{ route('verification.update', $order) }}" class="admin-card">
                @csrf @method('PATCH')
                <div class="admin-card-head"><x-admin.section-head icon="tag" title="Adjustments" description="Discount, coupon and courier charge can all be edited before confirmation."/></div>
                <div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <label><span style="font-size:12px;font-weight:600">Discount (৳)</span><input class="input" name="discount" type="number" step="0.01" min="0" value="{{ $order->discount ?? 0 }}"></label>
                    <label><span style="font-size:12px;font-weight:600">Courier charge (৳)</span><input class="input" name="shipping_total" type="number" step="0.01" min="0" value="{{ $order->shipping_total ?? 0 }}"></label>
                    <label><span style="font-size:12px;font-weight:600">Coupon code</span><input class="input" name="coupon_code" value="{{ $order->coupon_code }}" placeholder="e.g. SAVE100"></label>
                    <label><span style="font-size:12px;font-weight:600">Coupon discount (৳)</span><input class="input" name="coupon_discount" type="number" step="0.01" min="0" value="{{ $order->coupon_discount ?? 0 }}"></label>
                    <label style="grid-column:1/-1"><span style="font-size:12px;font-weight:600">Internal note</span><textarea class="input" name="notes" rows="2">{{ $order->notes }}</textarea></label>
                </div>
                <div class="admin-card-body" style="border-top:1px solid var(--a-border);display:flex;justify-content:flex-end">
                    <button class="btn btn-dark">Save adjustments</button>
                </div>
            </form>

            {{-- ───────── Items ───────── --}}
            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="cart" title="Items" description="Quantities only · prices come from the catalogue."/></div>
                <div class="atable-wrap">
                    <table class="atable">
                        <thead><tr><th>SKU</th><th>Item</th><th style="text-align:right;width:120px">Qty</th><th style="text-align:right;width:130px">Unit price</th><th style="text-align:right">Line total</th><th></th></tr></thead>
                        <tbody>
                        @foreach($order->items as $it)
                            <tr>
                                <td style="font-family:ui-monospace,monospace;font-size:12px">{{ $it->sku }}</td>
                                <td>{{ $it->name }}<div style="font-size:11px;color:var(--a-text-3)">{{ $it->variant_label }}</div></td>
                                <td>
                                    <form method="POST" action="{{ route('verification.item.update', [$order, $it]) }}" style="display:flex;justify-content:flex-end;gap:4px">
                                        @csrf @method('PATCH')
                                        <input class="input" name="qty" type="number" min="1" value="{{ $it->qty }}" style="width:70px;height:34px;padding:0 8px">
                                        <button class="btn btn-outline btn-sm" title="Save">✓</button>
                                    </form>
                                </td>
                                <td style="text-align:right;color:var(--a-text-2)">৳{{ number_format($it->unit_price,2) }}</td>
                                <td style="text-align:right;font-weight:600">৳{{ number_format($it->line_total,2) }}</td>
                                <td style="text-align:right">
                                    <form method="POST" action="{{ route('verification.item.remove', [$order, $it]) }}" onsubmit="return confirm('Remove this item?')">@csrf @method('DELETE')
                                        <button class="btn btn-danger btn-sm">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('verification.item.add', $order) }}" class="admin-card-body" style="border-top:1px solid var(--a-border);display:grid;grid-template-columns:1fr 100px 110px;gap:8px;align-items:end">
                    @csrf
                    <label><span style="font-size:11px;color:var(--a-text-2)">Add by SKU</span><input class="input" name="sku" placeholder="DF-XXXXXXX" required></label>
                    <label><span style="font-size:11px;color:var(--a-text-2)">Qty</span><input class="input" name="qty" type="number" min="1" value="1" required></label>
                    <button class="btn btn-outline">+ Add</button>
                </form>
            </div>

            {{-- ───────── Verification log ───────── --}}
            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="clock" title="Verification log"/></div>
                @if($order->verifications->isEmpty())
                    <x-admin.empty icon="clock" title="No call attempts yet"/>
                @else
                    <div class="atable-wrap">
                        <table class="atable">
                            <thead><tr><th>When</th><th>Agent</th><th>Outcome</th><th>Action</th><th>Summary</th></tr></thead>
                            <tbody>
                                @foreach($order->verifications as $v)
                                    <tr>
                                        <td>{{ $v->attempted_at->diffForHumans() }}</td>
                                        <td>{{ optional($v->agent)->name }}</td>
                                        <td><x-admin.pill :status="$v->call_outcome" :label="ucwords(str_replace('_',' ',$v->call_outcome))"/></td>
                                        <td><x-admin.pill :status="$v->action ?? 'edited'"/></td>
                                        <td style="font-size:12px;color:var(--a-text-2)">{{ $v->summary }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- ───────── Sidebar: confirm / cancel / totals ───────── --}}
        <div style="display:flex;flex-direction:column;gap:14px">
            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="check" title="Confirm order"/></div>
                <form method="POST" action="{{ route('verification.confirm', $order) }}" class="admin-card-body" style="display:flex;flex-direction:column;gap:8px">
                    @csrf
                    <label><span style="font-size:12px;font-weight:600">Call outcome</span>
                        <select class="input" name="call_outcome" required>
                            <option value="contacted">Customer contacted</option>
                            <option value="confirmed_by_message">Confirmed via message</option>
                        </select>
                    </label>
                    <label><span style="font-size:12px;font-weight:600">Summary</span><textarea class="input" name="summary" rows="2" placeholder="Verbal confirmation, address rechecked, etc."></textarea></label>
                    <button class="btn btn-dark btn-block">Confirm &amp; route to warehouse</button>
                </form>
            </div>

            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="x" title="Cancel order"/></div>
                <form method="POST" action="{{ route('verification.cancel', $order) }}" class="admin-card-body" style="display:flex;flex-direction:column;gap:8px">
                    @csrf
                    <label><span style="font-size:12px;font-weight:600">Cancellation reason</span>
                        <select class="input" name="reason" required>
                            @foreach(\App\Models\OrderMirror::CANCEL_REASONS as $rs)
                                <option value="{{ $rs }}">{{ ucwords(str_replace('_',' ',$rs)) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label><span style="font-size:12px;font-weight:600">Notes</span><textarea class="input" name="summary" rows="2"></textarea></label>
                    <button class="btn btn-danger btn-block">Cancel order</button>
                </form>
            </div>

            <div class="admin-card">
                <div class="admin-card-head"><x-admin.section-head icon="cart" title="Totals"/></div>
                <div class="admin-card-body" style="font-size:13px">
                    <div style="display:flex;justify-content:space-between"><span>Subtotal</span><strong>৳{{ number_format($order->subtotal,2) }}</strong></div>
                    <div style="display:flex;justify-content:space-between;color:var(--a-text-2)"><span>Discount</span><span>−৳{{ number_format($order->discount,2) }}</span></div>
                    @if($order->coupon_code)
                        <div style="display:flex;justify-content:space-between;color:var(--a-text-2)"><span>Coupon ({{ $order->coupon_code }})</span><span>−৳{{ number_format($order->coupon_discount,2) }}</span></div>
                    @endif
                    <div style="display:flex;justify-content:space-between;color:var(--a-text-2)"><span>Courier charge</span><span>৳{{ number_format($order->shipping_total,2) }}</span></div>
                    <hr style="border-color:var(--a-border)">
                    <div style="display:flex;justify-content:space-between;font-size:15px"><strong>Grand total</strong><strong>৳{{ number_format($order->grand_total,2) }}</strong></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Alpine.js for the edit toggle. Falls back gracefully if Alpine isn't loaded. --}}
    @push('scripts')
    <script defer src="/js/alpine.min.js"></script>
    <style>[x-cloak]{display:none !important}</style>
    @endpush
</x-app-layout>
