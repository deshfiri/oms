# DFOMS — DESHFIRI Order Management System

Laravel 13 workspace layer for DFCOMMERCE storefronts. Picking, packing,
dispatch, returns, damages, inventory and reports — driven by the storefront's
REST API + webhooks.

## Quick start

```bash
cd dfoms
composer install
cp .env.example .env  # (already done — APP_NAME=DFOMS, sqlite, queue=database)
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

Login as `admin@dfoms.test` / `password` (other seeded staff:
`picker@`, `packer@`, `dispatcher@`, `returns@`, `damage@`, `stock@`
— all password `password`).

## Connect a DFCOMMERCE store

1. In DFCOMMERCE Admin → API Clients, create credentials with the scopes
   needed (`orders.*`, `products.*`, `shipments.*`, `returns.*`, etc.).
2. In DFOMS → **Stores → Add store**, paste base URL, API key, API secret.
3. The webhook URL shown on the store row should be pasted into DFCOMMERCE
   Admin → Webhooks (events: `order.placed`, `order.updated`, `order.cancelled`,
   `payment.received`, `shipment.booked`, `shipment.delivered`,
   `return.requested`, `return.approved`, `return.completed`, `damage.recorded`,
   `inventory.low_stock`). Use the same webhook secret on both sides.
4. Click **Ping** on the store row to confirm the API credentials work.
5. Click **Sync** to back-fill orders/products/shipments mirrors.

## Background jobs

- Queue worker: `php artisan queue:work --queue=webhooks,sync,default`
- Periodic sync (cron, every 5 min): `php artisan dfoms:sync`
- The `routes/console.php` schedule already runs both via `php artisan schedule:run`.

## Module map

| Module        | Files |
|---------------|-------|
| Dashboard     | `app/Http/Controllers/DashboardController.php` + `resources/views/dashboard.blade.php` |
| Stores        | `StoreController` + `resources/views/stores/*` |
| Inbox / Orders| `InboxController`, `OrderController` + `inbox/*`, `orders/*` |
| Picking       | `PickingController` + `picking/*` |
| Packing + AWB | `PackingController` + `packing/*` |
| Dispatch      | `DispatchController` + `dispatch/*` |
| Tracking      | `TrackingController` + `tracking/*` |
| Returns / RMA | `ReturnsController` + `returns/*` |
| Damages       | `DamageController` + `damages/*` |
| Inventory     | `InventoryController` + `inventory/*` |
| Stock count   | `StockCountController` + `stock_counts/*` |
| Customers     | `CustomerController` + `customers/*` |
| Carts         | `CartsController` + `carts/*` |
| Reports       | `ReportController` + `reports/*` |
| Users         | `UserController` + `users/*` |
| Webhook in    | `app/Http/Controllers/Api/WebhookReceiverController.php` |

## Storefront client

`App\Services\Storefront\StorefrontClient` wraps `Http::withHeaders(...)` with
`X-API-Key` + `X-API-Secret`, 2× retry on connection failure, and throws
`StorefrontApiException` on non-2xx. Resources under
`App\Services\Storefront\Resources\` cover every endpoint listed in
DFCOMMERCE's `/api/v1`: orders, products, inventory, shipments, returns,
damages, customers, carts, webhooks.

## Tested

- All 77 routes register (`php artisan route:list`).
- Auth login, dashboard, stores, inbox, reports return HTTP 200.
- Webhook receiver: bad signature → 401, valid signature → 200, replay →
  `{"ok":true,"dedup":true}`.
- `ProcessWebhookJob` upserts orders + line items into mirror tables.
