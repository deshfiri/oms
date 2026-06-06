# Store website change: register OMS exchange orders

> Apply this on your **main store website** (the Laravel storefront the OMS
> connects to). It makes exchange replacement orders — created by the OMS —
> register as proper **exchange orders** that are linked back to the original
> order they replace.

---

## 1. What it's for

When a customer exchanges a delivered product, the OMS creates a **replacement
order** on your store via `POST /api/v1/orders`. That replacement already carries
an exchange marker, but your store currently has nowhere to store the **link to
the original order**. This change adds that link so the replacement is fully
registered as an exchange.

### What the OMS already sends (no change needed on the OMS)

```json
POST /api/v1/orders
{
  "customer": { "name": "...", "phone": "..." },
  "shipping":  { "address": "...", "city": "...", "zone": "inside_dhaka", ... },
  "items":     [ { "sku": "DF-YX3WIOND", "quantity": 1 } ],
  "payment_method": "cod",
  "shipping_total": 0,
  "status": "processing",          // skip verification — go straight to warehouse
  "source": "oms_exchange",        // ← marks it as an EXCHANGE (already stored today)
  "is_exchange": true,             // ← explicit flag
  "exchange_of": "DF-2026-048874", // ← the ORIGINAL order it replaces (NEW — store this)
  "placed_by": "CS Agent",
  "note": "Exchange replacement for DF-2026-048874"
}
```

Today your store **already stores `source = oms_exchange`** (so it's tagged as an
exchange). The only missing piece is storing **`exchange_of`** (and optionally
`is_exchange`).

---

## 2. The change (3 small edits)

### Edit 1 — Migration: add the `exchange_of` column

```bash
php artisan make:migration add_exchange_of_to_orders
```

In the generated migration:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            // Original order number this order is an exchange replacement for.
            $t->string('exchange_of', 64)->nullable()->after('source')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn('exchange_of');
        });
    }
};
```

```bash
php artisan migrate
```

### Edit 2 — `OrdersController@store`: accept + store it

File: `app/Http/Controllers/Api/V1/OrdersController.php`

**(a)** In the `$request->validate([...])` array, add these two lines:

```php
'is_exchange' => ['nullable', 'boolean'],
'exchange_of' => ['nullable', 'string', 'max:64'],
```

**(b)** In the `Order::create([...])` array — right after the existing
`'source' => $data['source'] ?? 'oms_manual',` line — add:

```php
'exchange_of' => $data['exchange_of'] ?? null,
```

> `source` already stores `oms_exchange`, so no need for a separate boolean
> column. If you prefer a real flag column too, add `is_exchange` the same way as
> `exchange_of` (a `boolean` column) and set `'is_exchange' => (bool) ($data['is_exchange'] ?? false)`.

### Edit 3 — `Order` model `$fillable` (only if your model uses `$fillable`)

File: `app/Models/Order.php` — add `'exchange_of'` to the `$fillable` array.
*(Skip this if the model uses `$guarded = []`.)*

---

## 3. Optional (recommended) — expose & display

### (a) Return `exchange_of` in the API so the OMS mirrors the link

File: `app/Http/Resources/Api/OrderResource.php` — add to the returned array:

```php
'source'      => $this->source,
'exchange_of' => $this->exchange_of,   // ← add this line
```

### (b) Show an "EXCHANGE" badge in the store admin orders list

Wherever you render order rows in the admin (e.g. `resources/views/admin/orders/index.blade.php`):

```blade
@if($order->source === 'oms_exchange')
    <span class="badge badge-warning">EXCHANGE</span>
    @if($order->exchange_of)
        <small>of {{ $order->exchange_of }}</small>
    @endif
@endif
```

---

## 4. Deploy

```bash
git pull                      # or copy the edited files into place
php artisan migrate
php artisan optimize:clear
# restart the app (php artisan serve / octane:reload / restart php-fpm)
```

---

## 5. Verify

After deploying, issue an exchange from the OMS (Order detail → **Issue exchange →
pick new product**). Then check the new replacement order on the store:

```sql
SELECT order_number, status, source, exchange_of
FROM orders
WHERE source = 'oms_exchange'
ORDER BY id DESC
LIMIT 5;
```

Expected:

| order_number   | status     | source       | exchange_of    |
|----------------|------------|--------------|----------------|
| DF-2026-XXXXXX | processing | oms_exchange | DF-2026-048874 |

That row **is** the exchange order: tagged `oms_exchange` and linked to the
original via `exchange_of`.

---

## 6. Summary checklist

- [ ] Migration adds `orders.exchange_of` (nullable, indexed)
- [ ] `OrdersController@store` validates `exchange_of` (+ optional `is_exchange`)
- [ ] `Order::create([...])` stores `exchange_of`
- [ ] `Order` `$fillable` includes `exchange_of` (if using `$fillable`)
- [ ] *(optional)* `OrderResource` returns `exchange_of`
- [ ] *(optional)* Admin shows an "EXCHANGE" badge + original-order link

**Net effect:** every OMS-issued exchange registers on your store as an exchange
order (`source = oms_exchange`) **linked to the original order** (`exchange_of`),
fulfilled through the normal Processing → courier → delivery flow.
