# OMS Client Panel: courier configuration fields

> Changes to make inside **DFOMS** (the OMS itself, not the storefront).
> Exposes the `default_courier` and `outside_dhaka_courier` store settings in
> the **Stores → Edit store** form so operators can choose which courier to use
> per store from the Client Panel.

---

## 1. The problem

The `stores` table already has two courier columns (added in migration
`2026_06_03_000009_add_default_courier_to_stores`):

| Column | DB default | Purpose |
|---|---|---|
| `default_courier` | `steadfast` | The courier used for every order from this store |
| `outside_dhaka_courier` | `NULL` | Override courier for non-Dhaka zones |

`CourierPicker` reads these correctly and now treats `default_courier` as the
authoritative setting (priority 2, above the order's `preferred_courier`).

**But the store form has no fields for either column.** The admin has no way to
change these values from the Client Panel — every store silently stays on
`steadfast`. The `StoreController::validateForm()` never accepts or saves them.

---

## 2. The fix (2 files)

### File 1 — `app/Http/Controllers/StoreController.php`

Add the two courier fields to `validateForm()`.

**Inside the `$rules` array, add:**

```php
'default_courier'        => ['required', 'string', Rule::in(array_keys(\App\Services\Couriers\CourierManager::ADAPTERS))],
'outside_dhaka_courier'  => ['nullable', 'string', Rule::in(array_keys(\App\Services\Couriers\CourierManager::ADAPTERS))],
```

The full `$rules` array after the change:

```php
use Illuminate\Validation\Rule;

$rules = [
    'dfid'                   => ['required','string','max:60', Rule::unique('stores','dfid')->ignore($store?->id)],
    'business_name'          => 'required|string|max:160',
    'domain_name'            => 'nullable|string|max:160',
    'customer_name'          => 'required|string|max:120',
    'customer_phone'         => 'required|string|max:40',
    'base_url'               => 'required|url',
    'api_key'                => 'required|string|max:120',
    'api_secret'             => $store ? 'nullable|string' : 'required|string',
    'webhook_secret'         => 'nullable|string',
    'is_active'              => 'nullable|boolean',
    'default_courier'        => ['required', 'string', Rule::in(array_keys(\App\Services\Couriers\CourierManager::ADAPTERS))],
    'outside_dhaka_courier'  => ['nullable', 'string', Rule::in(array_keys(\App\Services\Couriers\CourierManager::ADAPTERS))],
];
```

> `Rule::in(array_keys(CourierManager::ADAPTERS))` constrains input to the
> known slugs (`steadfast`, `pathao`, `redx`, `carrybee`, `manual`). Adding a
> new courier driver to `CourierManager::ADAPTERS` automatically makes it valid
> here too.

---

### File 2 — `resources/views/stores/form.blade.php`

Add a **Courier settings** section between the API credentials section and the
Save button.

**Insert this block before the `<div style="display:flex;gap:8px;...">` Save
button row:**

```blade
</div>

<div class="admin-card-head" style="border-top:1px solid var(--a-border)">
    <x-admin.section-head icon="truck" title="Courier settings" description="Which courier to use when dispatching orders for this store"/>
</div>
<div class="admin-card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">

    <label>
        <span style="font-size:12px;font-weight:600">Default courier *</span>
        <select name="default_courier" class="input">
            @foreach(\App\Services\Couriers\CourierManager::ADAPTERS as $slug => $class)
                <option value="{{ $slug }}" @selected(old('default_courier', $store->default_courier ?? 'steadfast') === $slug)>
                    {{ ucfirst($slug) }}
                </option>
            @endforeach
        </select>
        <p style="font-size:11px;color:var(--a-text-2);margin:4px 0 0">Used for every order unless an outside-Dhaka override applies.</p>
    </label>

    <label>
        <span style="font-size:12px;font-weight:600">Outside-Dhaka courier</span>
        <select name="outside_dhaka_courier" class="input">
            <option value="">— same as default —</option>
            @foreach(\App\Services\Couriers\CourierManager::ADAPTERS as $slug => $class)
                <option value="{{ $slug }}" @selected(old('outside_dhaka_courier', $store->outside_dhaka_courier) === $slug)>
                    {{ ucfirst($slug) }}
                </option>
            @endforeach
        </select>
        <p style="font-size:11px;color:var(--a-text-2);margin:4px 0 0">Override for orders with zone ≠ <code>inside_dhaka</code>. Leave blank to use the default.</p>
    </label>

</div>
<div class="admin-card-body" style="display:flex;gap:8px;margin-top:6px">
```

> The closing `</div>` at the top closes the previous `admin-card-body` block.
> The opening `<div class="admin-card-body" ...>` at the bottom opens the save
> button row, replacing the one that was there before.

---

## 3. Courier selection priority (for reference)

After these changes the dispatch path is:

| Priority | Source | When |
|---|---|---|
| 1 | `store.outside_dhaka_courier` | Order zone is not `inside_dhaka` AND the field is set |
| 2 | `store.default_courier` | Always — this is the Client Panel setting |
| 3 | `order.preferred_courier` | Fallback only when `default_courier` is empty (should not happen) |
| 4 | `steadfast` | Absolute last resort |

`CourierPicker::pickFor()` implements this order. No further changes needed there.

---

## 4. No migration needed

The `default_courier` and `outside_dhaka_courier` columns already exist on the
`stores` table (migration `2026_06_03_000009_add_default_courier_to_stores`).
`default_courier` has a DB-level default of `steadfast`, so existing rows are
already valid.

---

## 5. Deploy

```bash
# No migration — columns already exist.
php artisan optimize:clear
# Restart the app if needed (php artisan serve / octane:reload / restart php-fpm)
```

---

## 6. Verify

1. Open **DFOMS → Stores → Edit** for any store.
2. A **Courier settings** section appears with two dropdowns.
3. Change **Default courier** to `pathao`, save.
4. Place or re-process an order for that store — `CourierPicker` should pick `pathao`.
5. Check the created `courier_consignments` row: `courier_slug` should be `pathao`.

Quick DB check:

```sql
SELECT id, name, default_courier, outside_dhaka_courier FROM stores;
```

Expected: the row you just saved shows `pathao` (or whichever slug you chose).

---

## 7. Summary checklist

- [ ] `StoreController::validateForm()` validates `default_courier` (required, in ADAPTERS)
- [ ] `StoreController::validateForm()` validates `outside_dhaka_courier` (nullable, in ADAPTERS)
- [ ] `stores/form.blade.php` renders the **Courier settings** section with two `<select>` dropdowns
- [ ] Saving the store form persists both courier fields
- [ ] `CourierPicker` priority: outside-Dhaka override → default courier → preferred_courier → steadfast

**Net effect:** operators can open any store in the Client Panel, pick the
courier, save — and every subsequent dispatch for that store goes to the
chosen courier automatically.
