<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Thermal labels</title>
<style>
    /* 2 × 3 inch thermal label — portrait (2" wide, 3" tall). */
    @page { size: 2in 3in; margin: 0; }
    * { box-sizing: border-box; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
        -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { background:#f5f6f8; margin:0; padding:14px; }

    .toolbar { max-width: 360px; margin:0 auto 16px; display:flex; gap:8px; }
    .toolbar button, .toolbar a {
        font-size:13px;font-weight:600;padding:8px 16px;border-radius:6px;border:1.5px solid #0d0d0d;
        background:#0d0d0d;color:#fff;text-decoration:none;cursor:pointer;
    }
    .toolbar a.outline { background:#fff;color:#0d0d0d }

    .lbl {
        width: 2in; height: 3in; background:#fff;
        border: 1px solid #c0c0c0; padding: 5px 7px;
        display:grid;
        grid-template-rows: auto auto 1fr auto auto auto;
        gap: 2px; overflow: hidden;
        page-break-after: always;
        margin: 0 auto 14px;
    }

    /* HEADER */
    .lbl-head {
        display:flex; flex-direction:column; gap:3px;
        border-bottom:1px solid #000; padding-bottom:3px;
    }
    .hrow { display:flex; justify-content:space-between; align-items:center; }
    .oprow { display:flex; align-items:center; justify-content:space-between; gap:6px; border-top:1px solid #d0d0d0; padding-top:2px; }
    .oprow .oplogo { height:13px; width:auto; display:block; filter: brightness(0) saturate(100%); }
    .oprow .optext { font-size:7px; color:#000; letter-spacing:.4px; line-height:1; white-space:nowrap; font-weight:600; }
    .oprow .optext b { font-weight:800; color:#000; }
    .brand { display:flex; align-items:center; gap:3px; }
    .brand .mark {
        background:#e5252a; color:#fff; padding:2px 4px;
        font-size:9px; font-weight:800; letter-spacing:.4px; line-height:1;
    }
    .brand .biz {
        font-weight:800; font-size:11px; color:#000; letter-spacing:.2px;
        max-width: 130px; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;
    }
    .courier-pill {
        font-size:8.5px; font-weight:700; background:#000; color:#fff;
        padding:2px 4px; border-radius:2px; letter-spacing:.4px; text-transform:uppercase;
    }

    /* DFID + ORDER # row */
    .lbl-meta {
        display:flex; justify-content:space-between; align-items:center;
        font-family:ui-monospace,monospace; font-size:8.5px; color:#000;
        padding: 1px 0; border-bottom:1px dashed #999;
    }

    /* RECIPIENT + QR */
    .lbl-to {
        display:grid; grid-template-columns: 1fr 64px; gap:6px;
        font-size:9px; line-height:1.2;
    }
    .lbl-to .nm { font-weight:800; font-size:11px; line-height:1.12; margin-bottom:1px; }
    .lbl-to .ph { font-family:ui-monospace,monospace; font-weight:700; font-size:9.5px; margin-bottom:2px; }
    .lbl-to .addr { font-size:8.5px; line-height:1.32; }
    .lbl-to .loc  { font-size:8.5px; line-height:1.32; font-weight:600; }
    .lbl-to .qr   { display:flex; flex-direction:column; align-items:center; width:64px; }
    .lbl-to .qr img { width:60px; height:60px; }
    .lbl-to .qr .cid {
        font-family:ui-monospace,monospace; font-size:6.5px; font-weight:700;
        text-align:center; width:64px; word-break:break-all; line-height:1.05; margin-top:1px;
    }

    /* MIDDLE — order summary */
    .lbl-mid { border:1px solid #000; border-radius:3px; padding:2px 5px; }
    .mid-head { display:flex; justify-content:space-between; align-items:center;
        font-size:7px; font-weight:800; letter-spacing:1px; border-bottom:1px solid #000; padding-bottom:1px; margin-bottom:1px; }
    .mid-head .dt { font-family:ui-monospace,monospace; letter-spacing:.3px; }
    .mid-row { display:flex; justify-content:space-between; font-size:8px; line-height:1.28; }
    .mid-row span:last-child { font-family:ui-monospace,monospace; }
    .mid-total { display:flex; justify-content:space-between; font-size:9.5px; font-weight:800;
        border-top:1px solid #000; margin-top:1px; padding-top:1px; }
    .mid-total span:last-child { font-family:ui-monospace,monospace; }
    .mid-pay { font-size:7px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; margin-top:1px; }
    .mid-note { font-size:7.5px; line-height:1.2; margin-top:1px; border-top:1px dashed #999; padding-top:1px;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    /* ITEMS — single line */
    .lbl-items {
        font-size:8px; line-height:1.2;
        border-top:1px dashed #999; padding-top:2px;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .lbl-items b { font-size:8px; }

    /* CONSIGNMENT FOOTER (kept from the new design) */
    .ft { border-top:1px solid #000; padding-top:2px; display:flex; flex-direction:column; justify-content:flex-end; }
    .ft .k { font-size:6.5px; font-weight:800; letter-spacing:1.5px; }
    .ft img { width:100%; height:22px; margin-top:1px; }
    .ft .code { font-family:ui-monospace,monospace; text-align:center; font-size:9.5px; font-weight:800; letter-spacing:1.4px; }
    .ft .trk { font-family:ui-monospace,monospace; text-align:center; font-size:8px; letter-spacing:.4px; }

    @media print {
        body { background:#fff; padding:0; }
        .toolbar { display:none; }
        .lbl { border:none; margin:0; }
    }
</style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print ({{ $labels->count() }})</button>
        <a href="{{ url()->previous() }}" class="outline">← Back</a>
    </div>

    @foreach($labels as $lb)
        @php
            $o = $lb['order']; $cons = $lb['cons'];
            $loc = collect([$o->shipping_area, $o->shipping_city, $o->shipping_district, $o->shipping_postcode])
                ->map(fn ($v) => trim((string) $v))->filter()
                ->unique(fn ($v) => mb_strtolower($v))   // drop "Dhaka, Dhaka" duplicates
                ->implode(', ');
            $items = $o->items->take(3)->map(fn ($it) => $it->qty.'× '.\Illuminate\Support\Str::limit($it->name, 22))->implode(', ').($o->items->count() > 3 ? ' …' : '');
        @endphp
        <div class="lbl">
            <div class="lbl-head">
                <div class="hrow">
                    <div class="brand">
                        <span class="biz">{{ $o->store->business_name ?? $o->store->name }}</span>
                    </div>
                    <div class="courier-pill">{{ strtoupper($cons?->courier_slug ?? 'MANUAL') }}</div>
                </div>
                <div class="oprow">
                    <img class="oplogo" src="{{ asset('omslogo2.png') }}" alt="">
                    <span class="optext">POWERED BY <b>DESHFIRI.COM</b></span>
                </div>
            </div>

            <div class="lbl-meta">
                <span><strong>{{ $o->store->dfid }}</strong></span>
                <span>#{{ $o->order_number }}</span>
            </div>

            <div class="lbl-to">
                <div>
                    <div class="nm">{{ $o->shipping_name }}</div>
                    <div class="ph">{{ $o->shipping_phone }}</div>
                    <div class="addr">{{ $o->shipping_address_line }}</div>
                    <div class="loc">{{ $loc }}</div>
                </div>
                <div class="qr">
                    <img src="data:image/png;base64,{{ $lb['qr_b64'] }}" alt="QR">
                    <div class="cid">{{ $o->order_number }}</div>
                </div>
            </div>

            @php
                $disc = (float) ($o->discount ?? 0) + (float) ($o->coupon_discount ?? 0);
            @endphp
            <div class="lbl-mid">
                <div class="mid-head">
                    <span>ORDER SUMMARY</span>
                    <span class="dt">{{ optional($o->placed_at)->format('d M Y') ?: now()->format('d M Y') }}</span>
                </div>
                <div class="mid-row"><span>Items ({{ $o->items->sum('qty') }})</span><span>৳{{ number_format($o->subtotal, 0) }}</span></div>
                <div class="mid-row"><span>Delivery</span><span>৳{{ number_format($o->shipping_total, 0) }}</span></div>
                @if($disc > 0)<div class="mid-row"><span>Discount</span><span>−৳{{ number_format($disc, 0) }}</span></div>@endif
                <div class="mid-total"><span>TOTAL{{ $o->payment_method==='cod' ? ' (COD)' : '' }}</span><span>৳{{ number_format($o->grand_total, 0) }}</span></div>
                <div class="mid-pay">Payment · {{ strtoupper(str_replace('_',' ', $o->payment_method ?? 'cod')) }}{{ $o->payment_status ? ' · '.strtoupper($o->payment_status) : '' }}</div>
                @if($o->notes)<div class="mid-note"><b>Note:</b> {{ \Illuminate\Support\Str::limit($o->notes, 90) }}</div>@endif
            </div>

            <div class="lbl-items">
                <b>{{ $o->items->sum('qty') }} item(s):</b> {{ $items }}
            </div>

            <div class="ft">
                <span class="k">CONSIGNMENT</span>
                <img src="data:image/png;base64,{{ $lb['barcode_b64'] }}" alt="barcode">
                <div class="code">{{ $cons?->consignment_id ?: 'ORD '.$o->order_number }}</div>
                @if($cons?->tracking_code)<div class="trk">{{ $cons->tracking_code }}</div>@endif
            </div>
        </div>
    @endforeach
</body>
</html>
