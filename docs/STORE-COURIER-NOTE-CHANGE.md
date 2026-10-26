# Store website change: forward the OMS courier note to the courier

> Apply this on your **main store website** (the Laravel storefront the OMS
> connects to). The OMS Register order form has a **Courier note** (rider
> instructions, e.g. `URGENT: please deliver as soon as possible`). This change
> makes the store's courier booking send that note to the courier.

---

## 1. What it's for

When the OMS clicks **Send to courier**, it asks the store to book the parcel:

```json
POST /api/v1/orders/{order_number}/shipments
{
  "courier": "steadfast",
  "cod_amount": 1150,
  "courier_note": "URGENT: please deliver as soon as possible"   // ← NEW (only when the order has one)
}
```

Today the store validates only `courier`, `tracking_code`, `consignment_id` and
`cod_amount`, so `courier_note` is dropped and never reaches the courier.

The OMS also books **Steadfast directly** (when the store can't) — that path
already sends the note in Steadfast's `note` field. Only the store path needs
this change.

---

## 2. The change

### Edit 1 — accept the note

File: `app/Http/Controllers/Api/V1/OrdersController.php` → `createShipment()`

Add to the `$request->validate([...])` array:

```php
'courier_note' => ['nullable', 'string', 'max:480'],   // Steadfast's note limit
```

### Edit 2 — hand it to the courier booking

Still in `createShipment()`, where it calls
`CourierService::book($order, $data['courier'])`, pass the note so the courier
driver can use it. For example, set it on the order before booking (in memory —
no column needed):

```php
$order->setAttribute('oms_courier_note', $data['courier_note'] ?? null);
$booking = app(\App\Services\Couriers\CourierService::class)->book($order, $data['courier']);
```

### Edit 3 — put it in each courier's instruction field

File: `app/Services/Couriers/AbstractCourier.php` → `payloadFor()` builds
`special_instruction`. Prefer the OMS courier note when present:

```php
'special_instruction' => $order->oms_courier_note ?: /* existing value */ $order->special_instruction,
```

Each driver already sends `special_instruction` (or `item_description |
special_instruction`, see `STORE-COURIER-DESCRIPTION-CHANGE.md`) in its own
field — Steadfast `note`. **Check each driver you use** (Pathao, RedX,
CarryBee) against that courier's API docs and send the note only in a field the
courier documents for delivery instructions.

### Edit 4 — echo it back (so the OMS can confirm)

In the JSON response of `createShipment()` (`presentShipment()`), add:

```php
'courier_note' => $order->oms_courier_note,   // what was sent to the courier
```

The OMS marks the note **"sent via the store website"** only when the store
echoes the same text back; otherwise the order page says the store did not
confirm forwarding it.

---

## 3. Deploy

```bash
git pull                      # or copy the edited files into place
php artisan optimize:clear
```

No migration needed on the store.

---

## 4. Verify

1. OMS → Register order → fill **Courier note** → place the order → confirm it.
2. Processing → **Send to courier**.
3. In the courier panel (e.g. Steadfast), the parcel's note shows the text.
4. OMS → order page → **Courier note** card says *"Sent via the store website
   (it confirmed forwarding it to the courier)."*

---

## 5. Summary checklist

- [ ] `createShipment()` validates `courier_note` (nullable, max 480)
- [ ] The note is passed into `CourierService::book()`
- [ ] `payloadFor()` uses it as `special_instruction`; each driver sends it in a documented field
- [ ] `createShipment()` response echoes `courier_note`
