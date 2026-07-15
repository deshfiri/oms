# Courier Fraud Check in New Order Form — Deliverables

**Date:** 2026-07-15

## 1. Changed / new files

| File | What |
|---|---|
| `app/Services/Couriers/FraudCheck/CourierFraudCheckInterface.php` | **New.** Per-courier contract: `check(Store $store, string $phone): array` returning the normalized summary. |
| `app/Services/Couriers/FraudCheck/SteadfastFraudCheck.php` | **New.** Steadfast implementation (see §4). Defensive response parsing; never fabricates numbers. |
| `app/Services/Couriers/FraudCheck/FraudCheckService.php` | **New.** Orchestrator: phone normalization/masking, courier resolution, checker dispatch, risk rules. |
| `app/Services/Couriers/FraudCheck/FraudCheckException.php` | **New.** Failure with an operator-safe message + machine code (`no_courier`, `unsupported`, `not_configured`, `courier_error`, `invalid_response`, `timeout`). |
| `app/Services/Couriers/CourierPicker.php` | Added `defaultSlugForStore(Store): ?string` — store-level courier resolution from the same storefront settings `pickFor()` uses. `pickFor()` untouched. |
| `app/Http/Controllers/OrderCreationController.php` | Added `fraudCheck()` endpoint + private `logFraudCheck()`. No courier-specific logic in the controller — it delegates to the service. |
| `routes/web.php` | `POST /orders-new/fraud-check` → `orders-new.fraud-check`, inside the existing `role:social_media_manager,admin` group, plus `throttle:15,1`. |
| `resources/views/orders-new/create.blade.php` | Fraud Check button beside the phone field (disabled until store + valid phone; shows "Checking..."), result modal, Alpine logic. Phone field now accepts pasted `+880…` / `880…` and self-normalizes on change (`maxlength` 11→15; submit `pattern` unchanged). |
| `tests/Feature/FraudCheckTest.php` | **New.** 17 tests, all mocked — no live courier calls. |
| `database/migrations/2026_07_15_000002_add_courier_credentials_to_stores.php` | **New — see §2.** |

## 2. Migration details

`stores.courier_credentials_enc` (text, nullable) and `stores.courier_enabled` (boolean, default false) were referenced by the `Store` model since the original courier work but **no migration ever created them** — saving per-store courier credentials crashed on any DB built from migrations (found because the test schema failed). The new migration adds both, guarded with `Schema::hasColumn` so hand-patched environments no-op. Applied to the live DB.

## 3. Supported couriers

| Courier | Fraud check | Reason |
|---|---|---|
| **Steadfast** | ✅ supported | Same portal API + `Api-Key`/`Secret-Key` credential pair the existing booking adapter (`SteadfastCourier`) already uses. |
| Pathao | ❌ unsupported | The official courier API (`api-hermes.pathao.com`, the OAuth credentials in `config/couriers.php`) exposes no fraud/delivery-history endpoint. Fraud stats exist only on the merchant-portal API, which needs portal login credentials the OMS does not store. |
| RedX | ❌ unsupported | Booked through the store website; the OMS holds no RedX credentials. RedX's success-rate endpoint requires portal tokens. |
| CarryBee | ❌ unsupported | No known fraud-check API. |
| Manual | ❌ unsupported | Not an API courier. |

Unsupported couriers return: `Fraud checking is not supported by {Courier}.` — no invented endpoints.

## 4. Endpoint used per provider

- **Steadfast:** `GET https://portal.packzy.com/api/v1/fraud_check/{phone}` with the same `Api-Key` / `Secret-Key` headers as booking. Field names in the response are parsed defensively (`total_delivered|delivered|success_parcel…`); a 404 from the API is reported as "not supported by this courier", an unrecognizable body as "unexpected response". *Note: this endpoint is the community-documented Steadfast fraud check; if a Steadfast account/API revision lacks it, the UI degrades to the clean unsupported message — nothing breaks.*

## 5. Normalized response

```json
{
  "success": true,
  "store": "324 · Local",
  "courier": "Steadfast",
  "phone": "01712345678",
  "summary": { "total": 20, "delivered": 16, "returned": 1, "cancelled": 3, "pending": 0,
               "success_rate": 80, "return_rate": 20 },
  "risk": { "level": "low", "label": "Low Risk", "message": "Customer has a good delivery history." },
  "raw_reference": null
}
```

Failures: `{ "success": false, "message": "<operator-safe text>" }` (HTTP 200), validation `422`, no store access `403`, rate limit `429`.

## 6. Risk rules (only when the courier gives raw counts; a courier-provided risk status would win)

Pending parcels are excluded — rate = delivered ÷ (delivered + returned + cancelled).

- no history → **unknown** ("No delivery history was found…")
- only pending parcels → **unknown**
- ≤ 2 decided parcels → **low** if none failed, else **medium** — one return is never "fraud"
- rate ≥ 80 % → **low** · rate ≥ 50 % → **medium** · below → **high**

## 7. Permissions, validation, security

- Route: `auth` + `role:social_media_manager,admin` + `throttle:15,1` (per user).
- `store_id` required/exists; store must be active; user must pass `canAccessStore()` (SMM store pinning) or 403.
- Phone accepted as `017…`, `+88017…`, `88017…` (spaces/dashes tolerated), normalized server-side to `01XXXXXXXXX`, validated `^01[3-9]\d{8}$`.
- The courier and its credentials are resolved server-side only; `courier`/`api_key` fields sent from the client are ignored (tested).
- All courier calls stay on the backend; modal renders everything via Alpine `x-text` (escaped); no secrets, stack traces or raw errors reach the browser; technical detail goes to the log.
- Audit log per attempt: `action=courier_fraud_check`, user, store, courier, **masked** phone (`017****5678`), status (`success/failed/unsupported`), risk level, response time ms. Raw courier payloads are not stored.
- Informational only: creates no order and modifies no stock/customer/cart data (asserted in tests).

## 8. Test results

`php artisan test --filter=FraudCheckTest` → **17 passed (69 assertions)**, all courier/storefront HTTP faked.
Full suite → 41 passed, 1 pre-existing unrelated failure (`ExampleTest` expects `/` → 200; `/` has always redirected to the dashboard).

Covered: button visible · store/phone required · invalid phones rejected · formats normalized · store's default courier used · frontend cannot override courier/credentials · no default courier → clear error · missing credentials → clear error · unsupported courier message · normalized success response · balanced risk rules (6 scenarios) · API 500 graceful · API 404 → unsupported · unassigned user 403 · assigned SMM allowed · no order/data created · secrets never in response · audit row masked.

## 9. Manual verification (live local storefront, store “324 · Local”)

- Storefront settings resolution returned `steadfast` (real `GET /api/v1/settings/couriers` call).
- Full service path with no credentials → exact message "The selected store's courier integration is not configured properly." — the courier API is never called without credentials.
- `+8801712345678` normalized to `01712345678`; garbage input → clean validation message.
- All Blade views compile (`view:cache`); page render asserted in tests; the modal only toggles UI state — form fields, items and totals are untouched by design (button is `type="button"`).
- The live success path needs real Steadfast credentials (none exist in this environment) — add them under Stores → Edit courier credentials or `STEADFAST_API_KEY`/`STEADFAST_SECRET` in `.env`, then the same flow returns real numbers.

## 10. Notes

- One quirk found on the way: this app renders JSON exceptions only for `api/*` paths (`bootstrap/app.php`), so the endpoint validates via `Validator::make` and returns its own 422 JSON instead of `$request->validate()`.
