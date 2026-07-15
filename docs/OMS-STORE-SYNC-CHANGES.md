# OMS Store Sync & Data Cleanup — Change Notes

**Date:** 2026-07-15

## What was asked

1. Every 5 minutes, check each store's products and prices and auto-sync into the OMS so mirrors stay consistent.
2. When an admin presses Sync manually, run an urgent sync immediately.
3. When a product is deleted or its stock changes on the store, the OMS should add/remove/update the product accordingly on every sync.
4. Remove all static (seeded/demo) data from the database.
5. Do not break any existing behavior.

## Root causes found

- `SyncProductsJob` paginated only by `since_id`, so it fetched **new** products but never re-pulled **existing** ones. A price or stock change on an already-synced product never reached the OMS.
- The `updated_since` filter (both orders and products) was sent as ISO-8601 (`2026-07-15T04:03:30+00:00`). MySQL on the storefront compares that string raw and silently matches **zero rows** — so the "changed since" pass never returned anything. Verified live against the local storefront: the same window in `Y-m-d H:i:s` format matches correctly.
- Nothing ever **removed** a product mirror. The storefront soft-deletes products (they just vanish from its API, no webhook), so deleted products stayed in the OMS forever — store 3 carried 54 mirrors while its storefront catalogue was empty.

---

## Code changes

### Jobs

| File | Change |
|---|---|
| `app/Jobs/SyncProductsJob.php` | Rewritten. **Pass A** — new products by `since_id` cursor (as before). **Pass B** (new) — re-pulls products *changed* since the last product sync via `updated_since`, so price/stock edits on existing products flow in. New `full` constructor flag — walks the whole catalogue from id 0 and then **removes any mirror the walk didn't touch** (deletion reconcile; only runs when the walk completed without error, so an unreachable store never loses mirrors). Timestamps sent as plain UTC `Y-m-d H:i:s`. Updates the new `last_synced_products_at` watermark. |
| `app/Jobs/SyncOrdersJob.php` | One fix: `updated_since` now sent as plain UTC `Y-m-d H:i:s` instead of ISO-8601. Its Pass B (status changes on existing orders) had been silently matching nothing — masked until now by webhooks. |

### Console command (5-minute scheduler)

| File | Change |
|---|---|
| `app/Console/Commands/SyncStoresCommand.php` | The products job is now dispatched with `full = true`. Every 5-minute scheduled run (`dfoms:sync`, defined in `routes/console.php` — unchanged) converges mirrors exactly to the store's catalogue: adds, price/stock updates, and removals. |

### Controller

| File | Change |
|---|---|
| `app/Http/Controllers/StoreController.php` | `syncNow()` (per-store **Sync** button) now runs the products job with `full = true` → urgent full price refresh + deletion reconcile; clearer flash message. New `syncAll()` — same urgent sync for **every** active store, run inline so data is fresh when the page returns. `autoSync()` and everything else untouched. |

### Model

| File | Change |
|---|---|
| `app/Models/Store.php` | Added `last_synced_products_at` to `$casts` (datetime). Nothing else touched. |

### Routes

| File | Change |
|---|---|
| `routes/web.php` | Added `POST /stores-sync-all` → `StoreController@syncAll`, named `stores.sync-all`, inside the existing `role:admin` group. All existing routes unchanged. |

### View

| File | Change |
|---|---|
| `resources/views/stores/index.blade.php` | Added a "⟳ Sync all now" button in the page header next to "+ Add store". Per-row Ping/Sync/Edit/Delete buttons unchanged. |

---

## Migration (schema change)

| File | Change |
|---|---|
| `database/migrations/2026_07_15_000001_add_last_synced_products_at_to_stores.php` | **New.** Adds nullable `stores.last_synced_products_at` — the watermark for the products `updated_since` pass, kept separate from `last_sync_at` (which the orders job also bumps). Already migrated. Rollback: `php artisan migrate:rollback --step=1` drops only this column. |

---

## Database data changes (no schema, rows only)

Removed — seeded/demo/static data:

- **Stores 1 & 2** (`trendbd` / DFTRD001, `sneakerlab` / DFSNK001 — fake `*.example.com` stores from `WarehouseCostSeeder`). DB-level `cascadeOnDelete` removed their children: 2 warehouses, 45 product mirrors, 45 warehouse-stock rows, 45 warehouse product costs, 36 demo packed orders + 73 order items.
- **Warehouse billing rates** (2 rows): the seeded global default and the sneakerlab override. The override had to be deleted explicitly — its FK is `nullOnDelete`, so cascading would have turned it into a bogus second "global" row. The billing screen handles no-global-rate gracefully (treats rates as 0 until set).
- **54 stale product mirrors for store 3** — removed automatically by the new full-sync reconcile; those products are soft-deleted on the storefront, so the empty mirror now matches the store's real catalogue.
- **10 `audit_logs` rows** — feature-test artifacts (curl requests, fake scan codes `FAKE-99999` / `NONEXISTENT-9999`, status flips on demo orders that no longer exist).

Kept deliberately:

- **All 7 user accounts** (admin@admin.com + six `*@dfoms.test` role users). These are login credentials, not data — deleting them could lock you out. If you want the demo role users gone too, say so; they are trivial to remove or re-create (they come from `DatabaseSeeder`).
- **Store 3** ("324 · Local", the real storefront) and everything belonging to it.

> ⚠️ `database/seeders/WarehouseCostSeeder.php` still exists and `DatabaseSeeder` calls it — running `php artisan db:seed` will re-create all the demo stores/products/orders. Left untouched per "don't ruin previous anything"; delete or comment out `$this->call(WarehouseCostSeeder::class);` when you no longer want that possible.

---

## Resulting sync behavior

| Trigger | Cadence | What it does |
|---|---|---|
| Scheduler (`dfoms:sync`) | every 5 min | Full converge per store: new products added, prices/stock refreshed, store-deleted products removed. Orders/shipments/returns as before. |
| Page poller (`PollController@signature`) | ~60 s while any OMS page is open | Incremental products pass (adds + changes; cannot see deletions). Orders/consignments every ~3 s. Unchanged code — it benefits from the job fixes. |
| **Sync** button (per store) | manual | Urgent inline full sync — orders, consignments, products & prices (full walk + deletion reconcile), shipments, returns. |
| **⟳ Sync all now** button (stores page) | manual | Same urgent sync for every active store. |
| Webhooks | instant | Unchanged. |

## Verification performed (against the live local storefront on 127.0.0.1:8002)

- Created a product on the store → appeared in the OMS via incremental sync.
- Changed its price on the store (product behind the id cursor) → incremental sync updated the mirror. This exact case failed before the fix.
- Edited stock on the store → incremental sync updated the mirror's stock.
- Deleted a product on the store → full sync removed its mirror; the other product was kept intact with its updated stock.
- Corrupted a mirror price locally → urgent full sync restored the store's true price.
- All test data removed from both databases afterwards.
- Test suite: 24 passed, 1 pre-existing unrelated failure (`ExampleTest` expects `/` to return 200, but `/` redirects to the dashboard — predates these changes).
