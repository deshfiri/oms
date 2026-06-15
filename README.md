# DFOMS — DESHFIRI Order Management System

A Laravel-based warehouse and order management system built for DFCOMMERCE storefronts. It mirrors orders, products, customers and shipments from one or more client stores via REST API + webhooks, and drives the full post-order lifecycle — verification, picking, packing, dispatch, courier tracking, returns, exchanges, damages, and inventory.

---

## Tech stack

| Layer | Choice |
|---|---|
| Framework | Laravel 12 (PHP 8.2+) |
| Database | SQLite (dev) / MySQL (prod) |
| Queue | Database queue (or any Laravel-supported driver) |
| Auth | Laravel Breeze (session-based) |
| Frontend | Blade + vanilla CSS/JS (no build step required for core UI) |
| Couriers | Steadfast, Pathao, CarryBee, RedX, Manual |

---

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Login credentials (seeded):

| Email | Password | Role |
|---|---|---|
| admin@dfoms.test | password | admin |
| picker@dfoms.test | password | picker |
| packer@dfoms.test | password | packer |
| dispatcher@dfoms.test | password | dispatcher |
| returns@dfoms.test | password | returns_clerk |
| damage@dfoms.test | password | damage_clerk |
| stock@dfoms.test | password | stock_counter |

---

## Connect a DFCOMMERCE store

1. In DFCOMMERCE Admin → **API Clients**, create credentials with scopes: `orders.*`, `products.*`, `shipments.*`, `returns.*`, `inventory.*`, `customers.*`, `webhooks.*`.
2. In DFOMS → **Stores → Add store**, enter:
   - **DFID** — your internal merchant ID (e.g. `DF1024`)
   - **Business name**, **Customer name**, **Customer phone**
   - **Base URL** — the storefront root (e.g. `https://shop.example.com`)
   - **API key** and **API secret** from step 1
3. After saving, copy the **Webhook URL** shown on the store edit page and paste it into DFCOMMERCE Admin → Webhooks. Use the same webhook secret on both sides. Subscribe to events:
   `order.placed`, `order.updated`, `order.cancelled`, `payment.received`,
   `shipment.booked`, `shipment.delivered`, `return.requested`, `return.approved`,
   `return.completed`, `damage.recorded`, `inventory.low_stock`
4. Click **Ping** on the store row to confirm the API credentials work.
5. Click **Sync** to back-fill the orders / products / shipments mirrors.

---

## Generate an OTP for client disconnect

When a client storefront admin needs to disconnect from OMS (via their admin panel's OMS disconnect form), they require a one-time OTP that DFOMS must issue first.

1. Go to **Stores → Edit** on the relevant store.
2. Scroll to the **OTP Codes** section at the bottom.
3. Click **Generate OTP**, optionally set an expiry in hours, then click **Generate & show OTP**.
4. The plain OTP is shown **once** — copy it and give it to the client admin.
5. The table shows each code's status (Valid / Used / Expired) and when it was consumed. Valid codes can be revoked at any time.

The client's storefront must have its `license_server_url` set to this DFOMS instance and `license_server_api_key` set to the store's license API key (visible on the store edit page).

---

## Order lifecycle

```
pending_verification → confirmed → processing → packed → dispatched
    → shipped → out_for_delivery → delivered ┐
                                  ↓           └→ exchange_requested → exchange_processing
                              return_pending           → awaiting_return_product
                                  ↓                            ↓
                               returned → restockable     returned / lost
                                        → damaged
    (cancelled is reachable from most stages)
    (lost is reachable once the parcel has left the warehouse)
```

Status changes are pushed back to the storefront in real time (via `PushStorefrontStatusJob`) and written to the audit log. Inventory is adjusted automatically on key transitions (packed, returned, damaged).

---

## Staff roles

| Role | Access |
|---|---|
| `admin` | Everything — bypasses all role checks |
| `social_media_manager` | Place and delete new orders on behalf of customers |
| `customer_support` | Order verification queue (confirm / cancel / edit orders) |
| `warehouse_admin` | Processing queue, packing, lost items |
| `picker` | Picking sessions and scans |
| `packer` | Packing sessions, mark-packed, label printing |
| `dispatcher` | Dispatch handover, bulk handover, dispatch CSV, tracking |
| `returns_clerk` | Returns intake, inspection, exchanges |
| `damage_clerk` | Damage logging |
| `inventory_admin` | Inventory view, stock counts |
| `stock_counter` | Stock count sessions |

---

## Module map

| Module | Controller(s) | Views |
|---|---|---|
| Dashboard | `DashboardController` | `dashboard.blade.php` |
| Stores | `StoreController`, `StoreOtpController` | `stores/*` |
| All orders | `AllOrdersController` | `orders/all.blade.php` |
| Order detail | `OrderController` | `orders/show.blade.php` |
| Order creation | `OrderCreationController` | `orders-new/*` |
| Verification | `VerificationController` | `verification/*` |
| Processing | `ProcessingController` | `processing/*` |
| Packing | `PackingController` | `packing/*` |
| Dispatch | `DispatchController` | `dispatch/*` |
| Tracking | `TrackingController` | `tracking/*` |
| Returns | `ReturnsController` | `returns/*` |
| Exchanges | `ExchangeController` | `exchanges/*` |
| Damages | `DamageController` | `damages/*` |
| Lost | `LostController` | `lost/*` |
| Inventory | `InventoryController` | `inventory/*` |
| Stock counts | `StockCountController` | `stock_counts/*` |
| Customers | `CustomerController` | `customers/*` |
| Carts | `CartsController` | `carts/*` |
| Reports | `ReportController` | `reports/*` |
| Users | `UserController` | `users/*` |
| Label printing | `LabelController` | `labels/print.blade.php` |
| Scan (pack/dispatch/return) | `ScanController` | — |

---

## API endpoints

| Method | Path | Description |
|---|---|---|
| `POST` | `/api/webhooks/dfcommerce/{store}` | Inbound webhook from DFCOMMERCE storefront (HMAC verified) |
| `POST` | `/api/webhooks/courier/{slug}` | Inbound webhook from courier (Steadfast, Pathao, CarryBee) |
| `POST` | `/api/verify-otp` | OTP verification for client OMS disconnect (`X-Api-Key` header required) |

### OTP verify request / response

**Request headers:** `X-Api-Key: <store license_api_key>`

**Request body:**
```json
{
  "otp": "the-plain-otp-string",
  "requested_action": "revoke_api"
}
```

**Success response:**
```json
{
  "success": true,
  "allowed_actions": ["revoke_api"],
  "expires_at": "2026-06-15T14:00:00+00:00"
}
```

**Failure response:**
```json
{ "success": false }
```

---

## Storefront client

`App\Services\Storefront\StorefrontClient` wraps `Http::withHeaders(...)` with `X-API-Key` + `X-API-Secret`, 3-second connect timeout, 15-second read timeout, and 1 retry on connection failure. Throws `StorefrontApiException` on non-2xx.

Resource classes under `App\Services\Storefront\Resources\`:

`OrdersResource`, `ProductsResource`, `InventoryResource`, `ShipmentsResource`, `ReturnsResource`, `DamagesResource`, `CustomersResource`, `CartsResource`, `WebhooksResource`, `SettingsResource`, `ShippingResource`

---

## Courier integrations

`App\Services\Couriers\CourierManager` dispatches to the adapter matching the store's default courier setting (read from `settings.group=couriers, key=default_courier` on the storefront).

| Slug | Class |
|---|---|
| `steadfast` | `SteadfastCourier` |
| `pathao` | `PathaoCourier` |
| `carrybee` | `CarryBeeCourier` |
| `redx` | `RedxCourier` |
| `manual` | `ManualCourier` |

Credentials are stored encrypted per-store in `stores.courier_credentials_enc` and retrieved with `$store->credentialsFor('steadfast')`.

---

## Background jobs

```bash
# Queue worker
php artisan queue:work --queue=webhooks,sync,default

# Periodic sync (runs via schedule)
php artisan dfoms:sync

# Start the scheduler (every minute via cron or keep-alive)
php artisan schedule:run
```

The scheduler (defined in `routes/console.php`) runs `SyncStoresCommand` every 5 minutes to pull new orders, products, shipments and returns from all active stores.

---

## Key models

| Model | Table | Purpose |
|---|---|---|
| `Store` | `stores` | DFCOMMERCE client store credentials and config |
| `StoreOtpCode` | `store_otp_codes` | One-time OTPs issued per store for disconnect auth |
| `OrderMirror` | `orders_mirror` | Local copy of storefront orders |
| `OrderItemMirror` | `order_items_mirror` | Line items for each mirrored order |
| `OrderVerification` | `order_verifications` | CS agent call log per order |
| `ProductMirror` | `products_mirror` | Local product catalogue per store |
| `CustomerMirror` | `customers_mirror` | Local customer records |
| `WarehouseStock` | `warehouse_stock` | On-hand inventory per SKU |
| `CourierConsignment` | `courier_consignments` | Booked shipment records |
| `ShipmentLog` | `shipments_log` | Shipment events pushed from storefront |
| `WebhookEventLog` | `webhook_events_log` | Raw inbound webhook payloads (for replay) |
| `AuditLog` | `audit_logs` | Full lifecycle audit trail |
| `DamageLog` | `damage_logs` | Damaged item write-offs |
| `LostLog` | `lost_logs` | Lost parcel records |
| `Exchange` | `exchanges` | Exchange request workflow |
| `StockCount` / `StockCountLine` | `stock_counts` / `stock_count_lines` | Physical stock count sessions |
| `RmaWorkflow` | `rma_workflows` | Return merchandise authorization |
