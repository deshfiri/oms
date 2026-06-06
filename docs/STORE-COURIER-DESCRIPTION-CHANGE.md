# Store website change: courier description = product names

> Apply this on your **main store website** (the Laravel storefront the OMS
> connects to). When a parcel is registered with the courier, the courier's
> **description / note** field will contain the **product name(s)** instead of a
> generic "Order #…".

---

## 1. What it's for

When an order is sent to the courier, the store calls the courier API
(`CourierService → SteadfastCourier::book`). The courier (Steadfast) has a
**description / note** option. Today it sends the customer note (often empty).
This change makes that field show the actual products, e.g.:

```
1× Nike Air Max 270, 1× Sony WH-1000XM5, 1× Adidas Ultraboost 23
```

So the rider / courier panel shows exactly what's in the parcel.

---

## 2. The change (2 small edits)

### Edit 1 — build the description from the order's items

File: `app/Services/Couriers/AbstractCourier.php` → `payloadFor()`

**Replace** the current line:

```php
'item_description'  => 'Order '.$order->order_number,
```

**with:**

```php
'item_description'  => \Illuminate\Support\Str::limit(
    $order->items->map(fn ($i) => $i->quantity.'× '.$i->name)->implode(', ')
        ?: 'Order '.$order->order_number,   // fallback if an order somehow has no items
    250
),
```

> `Str::limit(..., 250)` keeps it within courier field limits. Make sure
> `use Illuminate\Support\Str;` is at the top of the file (or use the fully
> qualified `\Illuminate\Support\Str` as shown).
> Ensure the order has its items loaded — `payloadFor()` already reads
> `$order->items`, so `$order->loadMissing('items')` before booking is safe.

### Edit 2 — send that description in the courier's note field

File: `app/Services/Couriers/SteadfastCourier.php` → inside `book()`, the
`->post('https://portal.packzy.com/api/v1/create_order', [...])` payload.

**Replace:**

```php
'note' => $base['special_instruction'],
```

**with:**

```php
// Courier description = product names (+ any special instruction).
'note' => trim($base['item_description']
    .($base['special_instruction'] ? ' | '.$base['special_instruction'] : '')),
```

That's it — the Steadfast `note` (its description field) now carries the product
names, optionally followed by the customer's special instruction.

> **Pathao / CarryBee / other drivers:** if you use them, apply the same idea in
> their `book()` — put `$base['item_description']` into whatever field that
> courier calls the item/description/note (Pathao uses `item_description`,
> CarryBee uses `note`/`description`). `payloadFor()` now provides the product
> names in `item_description`, so each driver just needs to send it.

---

## 3. Deploy

```bash
git pull                      # or copy the edited files into place
php artisan optimize:clear
# restart the app (php artisan serve / octane:reload / restart php-fpm)
```

No migration needed — this only changes the courier API payload.

---

## 4. Verify

Book a real order to the courier (OMS → Processing → Send to courier). In your
Steadfast merchant panel (or the courier response), the parcel's **note /
description** should read the product names, e.g.
`1× Nike Air Max 270, 1× Sony WH-1000XM5`.

---

## 5. Summary checklist

- [ ] `AbstractCourier::payloadFor()` sets `item_description` = product names (qty × name, comma-separated, capped at 250 chars)
- [ ] `SteadfastCourier::book()` sends that in the `note` field
- [ ] *(if used)* Pathao/CarryBee drivers send `item_description` in their description field

**Net effect:** every courier registration carries the **product names** in the
courier's description field.
