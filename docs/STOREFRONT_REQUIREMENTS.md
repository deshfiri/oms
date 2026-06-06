# What DFOMS needs from the DFCOMMERCE storefront

This document lists exactly what the **storefront** must provide for the OMS
automation to run end-to-end without manual fixups. If a row is marked **must
have**, OMS can't function without it. **Nice-to-have** rows are quality-of-life
improvements that remove edge-case manual work.

> **Direction of integration**
> - OMS pulls from the storefront over `/api/v1/*` REST (push happens on schedule + on demand).
> - OMS receives push events at `/api/webhooks/dfcommerce/{store}` (HMAC-signed).
> - OMS pushes back: status changes, shipments, damages, inventory bulk-set, returns
>   lifecycle, customer-support edits, and (new) orders placed inside OMS by social
>   media managers.

---

## 1. Authentication & transport

| Requirement | Status | Notes |
|---|---|---|
| `X-API-Key` + `X-API-Secret` headers on every request | **must have** | Already implemented. |
| TLS on the API host (HTTPS) | **must have** | Production. Local dev can use HTTP. |
| Scope strings per endpoint (`orders.read`, `orders.write`, `products.read`, `inventory.write`, `returns.read`, `returns.write`, `damages.read`, `damages.write`, `customers.read`, `carts.read`, `shipments.read`, `shipments.write`, `payments.read`) | **must have** | Already implemented. |
| Standard error envelope `{ok: false, error: {code, message}}` | **must have** | Already implemented. |
| Rate limit ≥ 60 req/min per API client | **must have** | For 5-min sync cycles. |
| Per-IP allowlist on the API client (optional) | nice-to-have | Defence in depth. |
| Webhook target registration via `POST /api/v1/webhooks` | **must have** | Already implemented. |
| HMAC-SHA256 signature in `X-Webhook-Signature: sha256=<hex>` over the **raw** request body | **must have** | Already implemented. |
| Idempotency key `X-Webhook-Event-Id` (UUID) | **must have** | Already implemented. OMS dedupes against it. |
| Retry policy on webhooks (exponential backoff, ≥ 24h total) | **must have** | So OMS can recover from downtime. |
| Resend a missed event by id: `POST /api/v1/webhooks/{id}/replay/{event_id}` | nice-to-have | Saves manual reconciliation when OMS is down. |
| Bulk resend window: `POST /api/v1/webhooks/{id}/replay?since=…&until=…` | nice-to-have | After major OMS outage. |

---

## 2. Order payload shape

The storefront must return this shape on `GET /orders` and `GET /orders/{n}`:

```json
{
  "ok": true,
  "data": {
    "id": 12345,
    "order_number": "DF-2026-001234",
    "status": "confirmed",
    "payment_status": "paid",
    "payment_method": "cod",
    "currency": "BDT",
    "totals": {
      "subtotal": 1000,
      "discount": 100,
      "coupon_code": "SAVE10",
      "coupon_discount": 50,
      "shipping": 60,
      "tax": 0,
      "grand": 910,
      "paid": 0
    },
    "customer": { "name": "Khalid", "phone": "01700000000", "email": "k@x.com" },
    "shipping": {
      "name": "Khalid", "phone": "01700000000",
      "address": "21 Road",
      "area": "Banani", "city": "Dhaka",
      "district": "Dhaka", "postcode": "1213",
      "zone": "inside_dhaka"
    },
    "items": [
      { "id": 1, "product_id": 5, "sku": "DF-A1B2C3", "name": "Widget",
        "variant": null, "qty": 2, "unit_price": 500, "line_total": 1000 }
    ],
    "note": null,
    "placed_at": "2026-06-03T10:00:00+06:00",
    "updated_at": "2026-06-03T10:05:00+06:00"
  }
}
```

| Requirement | Status | Notes |
|---|---|---|
| Money on `totals.{subtotal, discount, shipping, tax, grand, paid}` (nested) | **must have** | OMS reads both nested and flat for back-compat. |
| `totals.coupon_code` + `totals.coupon_discount` | **must have** | Coupon editing is a verification feature. |
| `shipping.address` (single line) | **must have** | |
| `shipping.area, city, district, postcode, zone` | **must have** | District and postcode currently optional — make them populated when present. |
| `items[].sku` (canonical, unique per store) | **must have** | OMS uses SKU as the join key everywhere. |
| `items[].product_id` | **must have** | Used for inventory adjustments. |
| `items[].variant` (object or null) | nice-to-have | Currently treated as a label string. |
| `placed_at` (ISO 8601 with offset) | **must have** | |
| `updated_at` (ISO 8601 with offset) | **must have** | Used for the 24h re-pull safety net. |
| `cancel_reason` echoed on the order when cancelled | nice-to-have | OMS can store it, but a structured field is cleaner. |
| `return_reason` echoed when in return states | nice-to-have | Same as above. |

### Order status taxonomy

The storefront sends one of these strings. OMS maps them to its 18-status lifecycle.

| Storefront sends | OMS maps to | Notes |
|---|---|---|
| `pending` | `pending_verification` | New OMS-side state — order parks for CS call. |
| `confirmed` | `confirmed` | Auto-routes to Processing in OMS. |
| `processing` | `processing` | |
| `packed` | `packed` | OMS internally has `packing` (intermediate) — storefront doesn't need to know. |
| `shipped` | `shipped` | |
| `out_for_delivery` | `out_for_delivery` | nice-to-have but useful. |
| `delivered` | `delivered` | |
| `cancelled` | `cancelled` | |
| `returned` | `returned` | |
| `refunded` | maps to `cancelled` for now | Optional: add a `refunded` state on OMS if you need it separately. |

---

## 3. Product payload shape

```json
{
  "id": 1,
  "sku": "DF-PZEDO60N",
  "barcode": "8901234567890",
  "internal_barcode": null,
  "name": "Samsung Galaxy A55 5G 256GB",
  "slug": "samsung-galaxy-a55-5g-256gb",
  "type": "simple",
  "price": 52999,
  "sale_price": null,
  "currency": "BDT",
  "stock": {
    "managed": true,
    "quantity": 102,
    "status": "in_stock",
    "low_threshold": 5,
    "allow_backorders": false
  },
  "shipping": {
    "weight": 3.64,
    "length_cm": 0, "width_cm": 0, "height_cm": 0,
    "free": false
  },
  "bin_location": "A1-03",
  "image_url": "https://cdn.example.com/products/df-pzedo60n.jpg",
  "is_active": true,
  "updated_at": "2026-06-02T04:28:18+00:00"
}
```

| Requirement | Status | Notes |
|---|---|---|
| `sku` unique per store | **must have** | |
| `stock.{quantity, low_threshold, status, managed}` nested | **must have** | |
| `price` and `sale_price` separated | **must have** | OMS reads sale_price first when materializing line items for SMM-placed orders. |
| `barcode` for pack scanning (Code-128) | **nice to have** | OMS currently encodes the **tracking code** on the thermal label, not the product barcode. If you want item-level scan-verify at pack time, expose this. |
| `bin_location` (warehouse aisle/shelf string) | **need to add to DFCOMMERCE** | OMS displays this on the pick list so pickers know where to walk. Currently shows `—`. |
| `image_url` (or `images[].url`) | nice-to-have | Pick list thumbnails. |
| `weight` for the shipping label / courier rate calc | **must have** | Steadfast/Pathao need it. |

---

## 4. Shipping zones, areas & couriers

| Requirement | Status | Notes |
|---|---|---|
| Normalized zone strings: `inside_dhaka`, `outside_dhaka`, `sub_city` | **must have** | OMS's CourierPicker uses zone to choose the courier. |
| Endpoint to list available areas per zone | **need to add** | Currently OMS lets CS type free-text. Validating against the storefront's area list would prevent typos that break courier booking. |
| Endpoint to list cities/districts/postcodes | nice-to-have | Auto-suggest in the CS verification form. |
| Storefront default-courier setting echoed on `GET /stores/me` or similar | nice-to-have | OMS has its own `default_courier` / `outside_dhaka_courier` per store, but if the storefront already configures one we should mirror it. |
| Shipping rate calculator: `POST /shipping/quote {city, zone, weight, courier}` → `{shipping_total}` | **need to add** | The shipping fee is auto-calculated on the storefront. CS users in OMS see it but can no longer edit it. Without an API, OMS shows whatever was on the order at sync time. If the address changes during verification we can't recompute the fee. |

---

## 5. Coupons & discounts

| Requirement | Status | Notes |
|---|---|---|
| `GET /coupons/{code}` returning `{valid, type:percent|amount, value, max_uses, expires_at}` | **need to add** | When CS applies a coupon in the verification screen we currently take the user's word for it. With a lookup, OMS can validate the code, expiration, and compute the discount automatically. |
| `POST /coupons/{code}/redeem {order_number}` | **need to add** | So coupon usage counter increments on the storefront. |

---

## 6. Customer support / outreach

| Requirement | Status | Notes |
|---|---|---|
| `POST /orders/{n}/notes {body, author}` | **need to add** | So CS verification notes also appear in the storefront's order timeline. |
| `POST /orders/{n}/outreach {channel, outcome, summary}` | **need to add** | Logs the CS call attempt on the storefront. Currently it lives in `order_verifications` on OMS only. |
| `GET /customers/{id}/orders` | nice-to-have | OMS already pulls orders by customer email/phone — a dedicated endpoint is faster. |

---

## 7. Inbound order creation (new — for social media managers)

When an order is placed **inside OMS** (the SMM workflow), the storefront should
also know about it so reports, customer histories, and payments reconcile.

| Requirement | Status |
|---|---|
| `POST /orders` accepting `{customer, shipping, items[], payment_method, discount, coupon_code, shipping_total, source: "oms_manual", placed_by}` returning the canonical order_number | **need to add** |
| The storefront should mark `source = oms_manual` so analytics can separate organic vs CS-placed traffic | **need to add** |
| Webhooks fire for OMS-placed orders too (`order.placed` with same shape) | **must have** | Closes the loop. |

> Until this exists, OMS-placed orders live only in OMS and are not visible
> on the storefront. CS can still verify, the warehouse can still pack, and
> the courier still books — but the storefront's order list won't show them.

---

## 8. Shipments & courier

| Requirement | Status | Notes |
|---|---|---|
| `POST /orders/{n}/shipments {courier, tracking_code, …}` returning `{id, consignment_id, tracking_code, tracking_url, label_url}` | **must have** | Already implemented. |
| `PATCH /shipments/{id} {status}` for handover events | **must have** | Already implemented. |
| `label_url` field (PDF/PNG of the courier's own label) when the courier API returns one | nice-to-have | Otherwise OMS prints its own 2×3" thermal label. |
| Courier webhook proxying: storefront either accepts the courier's webhook itself and re-fires to OMS, **or** the courier hits OMS directly at `/api/webhooks/courier/{slug}` | **must have** | OMS supports both; pick one and document it. |

---

## 9. Returns

| Requirement | Status | Notes |
|---|---|---|
| `GET /returns`, `GET /returns/{id}`, `GET /returns/scan?code=` | **must have** | Already implemented. |
| `POST /returns`, `POST /returns/{id}/approve`, `/receive`, `/complete`, `/reject` | **must have** | Already implemented. |
| **Inbound tracking code** generated on `POST /returns/{id}/approve` and returned in the response | **must have** | Used by OMS for the return scan flow. |
| **Returns label print URL** (PDF) on the same response | nice-to-have | Currently OMS doesn't print a return label. |
| Return reason taxonomy: `customer_refused`, `customer_unreachable`, `wrong_address`, `courier_issue`, `delivery_failure` | **must have** | OMS uses the same strings. |

---

## 10. Damages

| Requirement | Status | Notes |
|---|---|---|
| `POST /damages {sku, product_id?, quantity, reason, photo_url?}` | **must have** | Already implemented. |
| Storefront decrements `stock.quantity` automatically | **must have** | Already implemented. |
| Storefront records the OMS damage record id | nice-to-have | For round-trip auditing. |

---

## 11. Inventory & multi-warehouse

| Requirement | Status | Notes |
|---|---|---|
| `GET /inventory` and `POST /inventory/bulk-set` | **must have** | Already implemented. |
| Multi-warehouse: `POST /inventory/bulk-set` accepts `{warehouse_code, items: [{sku, quantity}]}` | **need to add** | OMS has multi-warehouse buckets now (available / reserved / in_transit / returned / damaged) — the storefront only knows one total. To prevent over-selling, the storefront should accept per-warehouse adjustments. |
| Webhook `inventory.low_stock` (already documented) | **must have** | |
| Webhook `inventory.adjusted` with the new totals | nice-to-have | OMS otherwise polls. |

---

## 12. Webhook events the storefront must emit

These are the events OMS listens for at `POST /api/webhooks/dfcommerce/{store}`:

| Event | Payload | Status |
|---|---|---|
| `order.placed` | full order object | **must have** |
| `order.updated` | full order object | **must have** |
| `order.cancelled` | full order object (`status: cancelled`, `cancel_reason`) | **must have** |
| `order.refunded` | full order object | nice-to-have |
| `payment.received` | `{order_number, amount, method, txn_id, received_at}` | **must have** |
| `shipment.booked` | shipment object | **must have** |
| `shipment.delivered` | shipment object | **must have** |
| `return.requested` | return object | **must have** |
| `return.approved` | return object incl. `inbound_tracking_code` | **must have** |
| `return.completed` | return object | **must have** |
| `damage.recorded` | damage object | **must have** |
| `inventory.low_stock` | `{sku, quantity, threshold}` | **must have** |
| `inventory.adjusted` | `{sku, before, after, reason}` | nice-to-have |

---

## 13. Pagination, cursor, and re-pull semantics

OMS syncs in this pattern:

```
GET /orders?since_id=<lastSeenId>&updated_since=<24hAgo>&limit=100
```

| Requirement | Status |
|---|---|
| `since_id` cursor — strictly increasing integer order id | **must have** |
| Response `paging.next_since_id` so OMS can keep walking | **must have** |
| `updated_since` filter — re-pull recently changed rows even if they're older than `since_id` | **must have** |
| Same pattern on `/products` and `/shipments` | **must have** |

---

## 14. Storefront merchant metadata (so OMS can show "DFID · Business")

| Requirement | Status |
|---|---|
| `GET /me` (or `/stores/me`) returning `{dfid, business_name, domain_name, customer_name, customer_phone}` | **need to add** |
| | Currently OMS takes these from its own Stores form. If the storefront exposes them, OMS can auto-fill on first connect. |

---

## 15. Address format alignment (OMS storefront mirror)

The OMS verification screen now uses the DFCOMMERCE address format exactly:

```
Recipient name      |  Recipient phone
Address line (one)
Area  |  City  |  District  |  Postcode
Zone (inside_dhaka | outside_dhaka | sub_city)
```

If the storefront adds new fields (e.g. building number, floor, landmark),
they need to be exposed on the order payload so OMS can mirror them.

---

## 16. What OMS does **not** need from the storefront

For clarity, these aren't required:

- The storefront does **not** need to know about OMS's `packing` intermediate state.
- The storefront does **not** need to know about `restockable`, `damaged`, `exchange_*`, `awaiting_return_product`, or `lost` — those are OMS-side life-cycle states that get reflected back through `damages`, `returns/complete`, and webhook calls instead.
- The storefront does **not** need to know which OMS user packed an order — that's OMS-internal.

---

## Sign-off checklist for the storefront engineer

- [ ] HMAC signature, dedup id, retry policy on webhooks
- [ ] All 12 webhook events emitting on the right state changes
- [ ] Nested `totals`, `customer`, `shipping`, `items[]` on order payload
- [ ] Nested `stock` on product payload + `bin_location`
- [ ] Coupon lookup + redeem endpoints
- [ ] Customer outreach + notes endpoints
- [ ] `POST /orders` for OMS-placed orders (source=oms_manual)
- [ ] Multi-warehouse inventory bulk-set
- [ ] Shipping quote endpoint
- [ ] Merchant metadata endpoint (DFID, business name, etc.)
- [ ] Status taxonomy matches the table in §2

Once those are green, OMS automation flows are zero-touch from order placement
through delivery, return, exchange, restock, and lost-product handling.
