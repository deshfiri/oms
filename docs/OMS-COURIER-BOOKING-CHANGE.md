# Storefront change: let the OMS book couriers via the API

> Portable guide. Apply this to any Laravel storefront that an external **OMS**
> (Order Management System) connects to, so the OMS's "Send to courier" action
> registers the parcel with the real courier and gets back the **real
> consignment id + tracking** — no fakes, no placeholders.

---

## 1. The problem

The storefront exposes a shipments endpoint:

```
POST /api/v1/orders/{order_number}/shipments
```

In its original form this endpoint was **record-only** — it *required* a
`tracking_code` in the request and simply saved whatever it was given. It did
**not** call the courier.

So when the OMS clicked **"Send to courier"** (which sends only
`{ courier, cod_amount }`, no tracking code), the storefront replied:

```json
{ "message": "The tracking code field is required.",
  "errors": { "tracking_code": ["The tracking code field is required."] } }
```

→ The OMS could never trigger a real courier booking. Consignment stayed empty.

Meanwhile the storefront **admin panel** *could* book couriers — because it
calls `CourierService::book()`. The API just never did.

---

## 2. The fix (one method)

Make `createShipment` work in **two modes**:

| Request | Behaviour |
|---|---|
| **no** `tracking_code` | **Register** with the courier now via `CourierService::book()`, store the real consignment id + tracking it returns. *(This is what the OMS uses.)* |
| `tracking_code` present | **Record** an already-booked shipment (back-compatible). |

Plus:
- **Idempotent** — if the order already has a shipment, return it (no double-booking).
- **Honest** — if the courier returns no id (e.g. account inactive), return `502`; **never store an empty/placeholder consignment**.

### File: `app/Http/Controllers/Api/V1/OrdersController.php`

**Before:**

```php
public function createShipment(Request $request, Order $order): JsonResponse
{
    $data = $request->validate([
        'courier'        => ['required', 'string', 'max:32'],
        'tracking_code'  => ['required', 'string', 'max:64'],   // ← required
        'consignment_id' => ['nullable', 'string', 'max:64'],
        'cod_amount'     => ['nullable', 'numeric', 'min:0'],
    ]);

    $shipment = $order->shipments()->create([
        'courier'        => $data['courier'],
        'tracking_code'  => $data['tracking_code'],
        'consignment_id' => $data['consignment_id'] ?? null,
        'cod_amount'     => $data['cod_amount'] ?? ($order->payment_method === 'cod' ? $order->grand_total : 0),
        'status'         => 'booked',
        'booked_at'      => now(),
    ]);

    return response()->json(['ok' => true, 'data' => [ /* shipment fields */ ]], 201);
}
```

**After:**

```php
/**
 * POST /orders/{order}/shipments   { courier, tracking_code?, consignment_id?, cod_amount? }
 *
 *  • Register — no tracking_code ⇒ register with the courier (CourierService) and
 *    store the REAL consignment id + tracking it returns. (Used by the OMS.)
 *  • Record  — tracking_code given ⇒ just record an already-booked shipment.
 *
 * Idempotent: returns the existing shipment if the order already has one.
 */
public function createShipment(Request $request, Order $order): JsonResponse
{
    $data = $request->validate([
        'courier'        => ['required', 'string', 'max:32'],
        'tracking_code'  => ['nullable', 'string', 'max:64'],   // ← now optional
        'consignment_id' => ['nullable', 'string', 'max:64'],
        'cod_amount'     => ['nullable', 'numeric', 'min:0'],
    ]);

    // Never book the same order twice.
    if ($existing = $order->shipments()->latest('id')->first()) {
        return response()->json(['ok' => true, 'data' => $this->presentShipment($order, $existing)], 200);
    }

    $trackingCode  = $data['tracking_code'] ?? null;
    $consignmentId = $data['consignment_id'] ?? null;

    // No tracking code ⇒ register with the courier now and capture the real IDs.
    if (! $trackingCode) {
        try {
            $booking       = app(\App\Services\Couriers\CourierService::class)->book($order, $data['courier']);
            $consignmentId = ($booking['consignment_id'] ?? '') ?: $consignmentId;
            $trackingCode  = ($booking['tracking_code']  ?? '') ?: $trackingCode;
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => ['message' => 'Courier registration failed: '.$e->getMessage()],
            ], 502);
        }
    }

    // The courier must return a real id — never store an empty/placeholder one.
    if (! $trackingCode && ! $consignmentId) {
        return response()->json([
            'ok'    => false,
            'error' => ['message' => 'Courier did not return a consignment — the courier account/credentials may be inactive.'],
        ], 502);
    }

    $shipment = $order->shipments()->create([
        'courier'        => $data['courier'],
        'tracking_code'  => $trackingCode ?: $consignmentId,
        'consignment_id' => $consignmentId ?: null,
        'cod_amount'     => $data['cod_amount'] ?? ($order->payment_method === 'cod' ? $order->grand_total : 0),
        'status'         => 'booked',
        'booked_at'      => now(),
    ]);

    return response()->json(['ok' => true, 'data' => $this->presentShipment($order, $shipment)], 201);
}

/** Shape a shipment row for the API response. */
protected function presentShipment(Order $order, \App\Models\Shipment $shipment): array
{
    return [
        'order_number'   => $order->order_number,
        'id'             => $shipment->id,
        'shipment_id'    => $shipment->id, // back-compat alias
        'consignment_id' => $shipment->consignment_id,
        'courier'        => $shipment->courier,
        'tracking_code'  => $shipment->tracking_code,
        'tracking_url'   => $shipment->trackingUrl(),   // or build the URL yourself
        'label_url'      => $shipment->labelUrl(),      // optional, can be null
        'status'         => $shipment->status,
    ];
}
```

> If your `Shipment` model has no `trackingUrl()` / `labelUrl()` helpers, replace
> them with `null` or build the URL inline, e.g.
> `'tracking_url' => $shipment->tracking_code ? 'https://steadfast.com.bd/t/'.$shipment->tracking_code : null`.

---

## 3. What this depends on (must already exist)

This change only *triggers* booking — the real work lives in your courier layer,
which must already be present and correct:

1. **`CourierService::book(Order $order, string $courier): array`** — routes to the
   courier driver and returns:
   ```php
   [
     'consignment_id' => '256423473',          // real, from the courier API ('' if none)
     'tracking_code'  => 'SFR260604ST...BBD',  // real, from the courier API ('' if none)
     'status'         => 'booked',
     'stubbed'        => false,                 // true if it couldn't reach the courier
     'raw'            => [...],
   ]
   ```

2. **Courier drivers** (e.g. `SteadfastCourier`) that actually call the courier API
   using your stored credentials (Settings / `.env`).

3. **Honest stub** — when the courier can't be reached, the driver/`AbstractCourier::stub()`
   must return **empty** ids (not fabricated ones):
   ```php
   protected function stub(Order $order, string $reason = ''): array
   {
       // IDs are never generated locally — they must come from the courier API.
       return ['consignment_id' => '', 'tracking_code' => '', 'status' => 'pending',
               'raw' => ['stub' => true, 'reason' => $reason], 'stubbed' => true];
   }
   ```
   This is what makes the `502 "account may be inactive"` path correct instead of
   silently saving a fake `ST-CID-…` number.

4. The courier account/keys must be **active**. If the courier API rejects (e.g.
   Steadfast `401 "Account is not active!"`), this endpoint returns `502` — by
   design — rather than faking an id. Reactivate the account to get real ids.

---

## 4. Response contract the OMS expects

On success (`201`, or `200` if idempotent), return at least:

```json
{
  "ok": true,
  "data": {
    "order_number": "DF-2026-235124",
    "consignment_id": "256423473",
    "tracking_code": "SFR260604STDCDAB6BBD",
    "tracking_url": "https://steadfast.com.bd/t/SFR260604STDCDAB6BBD",
    "status": "booked"
  }
}
```

The OMS also reads courier data embedded in the **order detail** response, so make
sure `GET /api/v1/orders/{order_number}` includes a `shipments[]` array:

```json
{
  "ok": true,
  "data": {
    "order_number": "DF-2026-235124",
    "status": "processing",
    "shipments": [
      {
        "id": 17, "courier": "steadfast",
        "consignment_id": "256423473",
        "tracking_code": "SFR260604STDCDAB6BBD",
        "tracking_url": "https://steadfast.com.bd/t/SFR260604STDCDAB6BBD",
        "status": "booked", "booked_at": "2026-06-04T10:02:12+00:00"
      }
    ]
  }
}
```

> The OMS poller pulls order details for orders awaiting a consignment, so the
> real id appears automatically within seconds even without the click response.

---

## 5. API permissions

The endpoint sits behind `api.client:orders.write`. The OMS's API client must have
the `orders.write` scope. (If the OMS already reached the old endpoint and got a
`422` validation error — not `403` — the scope is already granted.)

No new scope, courier config, or recipient data is needed: `CourierService::book()`
uses the order's own recipient name/phone/address and your stored courier credentials.

---

## 6. Deploy & verify

```bash
# on the storefront server
git pull            # or copy the updated OrdersController.php into place
php artisan optimize:clear
# restart the app (php artisan serve / octane:reload / restart php-fpm)
```

Verify from the OMS (or with curl using the OMS API key/secret):

```bash
curl -X POST "https://YOUR-STORE/api/v1/orders/DF-2026-235124/shipments" \
  -H "X-API-Key: <oms_key>" -H "X-API-Secret: <oms_secret>" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"courier":"steadfast","cod_amount":0}'
```

- **Account active** → `201` with a real `consignment_id` + `tracking_code`.
- **Account inactive** → `502` with `"...account/credentials may be inactive."` (correct — no fake).

Then in the OMS: **Processing → Send to courier** → the real consignment + tracking
appear in Processing → Packing → Dispatch within ~1 second.

---

## 7. Summary checklist

- [ ] `tracking_code` validation changed `required` → `nullable`
- [ ] When no `tracking_code`, call `CourierService::book($order, $courier)`
- [ ] Store the returned real `consignment_id` / `tracking_code`
- [ ] Return `502` (never store empty) when the courier returns no id
- [ ] Idempotent: return existing shipment if one exists
- [ ] `stub()` returns empty ids (no local fabrication)
- [ ] Courier driver hits the real courier API with active credentials
- [ ] Order detail (`GET /orders/{n}`) includes `shipments[]`
- [ ] OMS API client has `orders.write` scope

**Net effect:** OMS "Send to courier" → storefront registers with the courier →
real consignment + tracking returned → shown in the OMS in seconds. Nothing faked.
