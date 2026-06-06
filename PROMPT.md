# DFOMS — Order Lifecycle Specification

A clean rewrite of the build prompt for Claude or any other engineer working on
the system.

## Mission

Build an Amazon-style Order Management System that walks every order from
checkout through delivery (or return / exchange / loss) without any operator
having to type a status manually.

## Lifecycle (17 statuses)

Every order transitions through a strict state machine. The UI only ever
offers next-allowed buttons; illegal jumps are blocked.

| # | Status | Who moves it | What happens |
|---|---|---|---|
| 1 | **Pending Verification** | Customer Support | New order lands here. CS phones the customer to confirm intent. |
| 2 | **Confirmed** | CS | Customer agreed. Auto-routes to the warehouse. |
| 3 | **Cancelled** | CS / warehouse | Customer refused, duplicate, fraud, out-of-stock, etc. Records a reason. |
| 4 | **Processing** | (auto from Confirmed) | Sitting in the warehouse queue waiting to be packed. |
| 5 | **Packed** | Warehouse | Item physically boxed, courier consignment booked, label printed. |
| 6 | **Dispatched** | Dispatcher | Handed off to the courier driver. |
| 7 | **Shipped** | Courier webhook | Courier confirms pickup / in-hub. |
| 8 | **Out For Delivery** | Courier webhook | Parcel with the last-mile rider. |
| 9 | **Delivered** | Courier webhook | Customer signed for it. `delivered_at` set. |
| 10 | **Return Pending** | Courier webhook / dispatcher | Customer refused, unreachable, wrong address, courier issue, delivery failure. |
| 11 | **Returned** | Warehouse scan | Parcel physically back at the warehouse. |
| 12 | **Restockable** | Returns clerk | Inspected, item is fine, auto-restocked into available inventory. |
| 13 | **Damaged** | Returns clerk / damage clerk | Inspected and unsellable. Photos, qty, reason, responsible party. |
| 14 | **Exchange Requested** | CS | Customer wants a different SKU/size. |
| 15 | **Exchange Processing** | Warehouse | Replacement order built (linked to the original). |
| 16 | **Awaiting Return Product** | (auto) | Original order parked here until the old item arrives back. |
| 17 | **Lost** | Warehouse / dispatcher | Lost by warehouse, courier, or vendor. Records compensation. |

## Customer Support — Verification

When CS opens a *Pending Verification* order they can:

- **Edit customer & delivery** — name, phone, address line, area, city, zone.
- **Edit items** — add new lines, change qty / unit price, remove lines.
- **Apply price changes** — discount amount, coupon code + coupon discount,
  courier shipping charge.
- **Add internal notes** — free-form.
- **Confirm** — fires `Pending Verification → Confirmed`, then auto-fires
  `Confirmed → Processing`. The order now belongs to the warehouse.
- **Cancel** — required cancel reason (customer changed mind, duplicate,
  unable to contact, fraudulent, price dispute, out of stock, other).

Every CS interaction is logged in `order_verifications` (call outcome,
diff of what changed, summary). Inventory automatically reserves stock on
Confirm and releases it on Cancel.

## Warehouse — Processing → Packed

The Processing queue supports:

- **Filter** by store, packer, or text search.
- **Bulk select** rows.
- **Bulk assign packer** — sets `packer_user_id` on every selected order.
- **Bulk pack & print labels** — pick a courier from the list (Steadfast,
  Pathao, RedX, CarryBee, Manual), the system:
  1. Calls the courier API and gets back `consignment_id`, `tracking_code`,
     `tracking_url`, `label_url` (or generates a code if running offline).
  2. Stores a `courier_consignments` row.
  3. Flips each order through the state machine to **Packed**.
  4. Redirects to the batch label print page.

There is also a dedicated **Pack scan** screen — paste or scan many order
numbers / tracking codes and they all flip Processing → Packed in one POST.

## Thermal Label (2 × 3 inch)

Each label is laid out for direct-to-paper printing at 3" × 2":

```
┌───────────────────────────────────────────────────────────┐
│ [DF] Business name        DFID · order#       STEADFAST   │  ← header
├───────────────────────────────────────────────────────────┤
│ Recipient                       ┌─────────┐               │
│ Name + phone                    │  QR     │   ← scan-able │
│ Address                         │ 80×80   │  consignment  │
│ Area, City                      └─────────┘               │
│ COD: ৳XXX                       CONS-ID-HERE              │
├───────────────────────────────────────────────────────────┤
│ N item(s): qty× Product name, qty× Product, …             │
├───────────────────────────────────────────────────────────┤
│ ████ ████ ████ ████ ████ ████  ← Code-128 barcode         │
│            TRACKING-CODE                                  │
└───────────────────────────────────────────────────────────┘
```

- **Brand block** — `[DF]` red + dark logo + business name.
- **DFID** — the merchant identifier from the Stores record.
- **Order #** — invoice number.
- **Courier pill** — courier name in uppercase.
- **Recipient block** — name / phone / address / area-city / COD amount.
- **QR code** — encodes the courier tracking URL (so the customer or rider
  can scan to track).
- **Consignment ID** — text under the QR.
- **Items summary** — up to 3 line items inline.
- **Code-128 barcode** — encodes the tracking code; the warehouse uses this
  for the pack-scan and dispatch-scan flows.

Routes:
- `/labels/{order}` — single label.
- `/labels?ids=1,2,3` — bulk label page (one per page-break for the
  thermal printer).

## Dispatch → Tracking

- Packed parcels group by courier on the Dispatch page.
- **Handover** per parcel or **Bulk dispatch scan** (paste/scan tracking
  codes) flips Packed → Dispatched.
- Courier webhooks at `POST /api/webhooks/courier/{slug}` map their native
  status names (e.g. Steadfast's `in_hub`, `pickup_assigned`) to our
  taxonomy (`hub_received`, `picked_up`, etc.) and forward up to the order:
  Dispatched → Shipped → Out For Delivery → Delivered.
- The Tracking page shows live consignments + an exceptions panel for
  delivery_failed, return_initiated, returned, lost.

## Returns Workflow

1. Courier reports a failed delivery → order moves to **Return Pending**
   with a reason (`customer_refused`, `customer_unreachable`,
   `wrong_address`, `courier_issue`, `delivery_failure`).
2. Returns clerk scans the parcel at intake → **Returned**.
3. Inspect dialog:
   - **Restock** (good condition) → moves to **Restockable**, inventory
     bucket flips `returned → available` automatically.
   - **Damage** (unsellable) → moves to **Damaged**, inventory bucket
     flips `returned → damaged`. Photos, condition grade (A/B/C/D),
     responsible party (warehouse / courier / vendor), reason and qty are
     captured. A row is written to `damage_log`.

## Exchanges

From any **Delivered** order, the CS or returns clerk opens an exchange:

1. Original order moves Delivered → **Exchange Requested**.
2. A replacement order is auto-cloned (same items by default) with status
   **Exchange Processing**.
3. Original moves to **Awaiting Return Product** and waits.
4. The replacement order goes through the normal warehouse → courier flow.
5. When the old item arrives back, the original is scanned to **Returned**
   and then **Restockable** or **Damaged** by the inspector.

The `exchanges` table keeps the link between original and replacement; the
`exchange_of_order_id` column on the replacement points back at the original.

## Lost Product

Any in-transit order (Packed, Dispatched, Shipped, Out For Delivery,
Return Pending, Awaiting Return Product) can be moved to **Lost** by an
authorized user. A `lost_log` row captures:

- Responsible party — `warehouse` / `courier` / `vendor`.
- Reason text.
- Compensation amount + status (`pending` / `paid` / `waived`).

Inventory subtracts the lost qty from whatever bucket it was sitting in.

## Damage (manual or automatic)

- **Automatic** — return inspection writes a `damage_log` row when the
  decision is "damage".
- **Manual** — `/damages/create` for warehouse / picker / packer / returns
  clerks to log a unit that broke off-flow (dropped at picking, expired,
  etc.). When posted, the inventory engine subtracts from `available` and
  adds to `damaged`, then mirrors the entry to DFCOMMERCE via
  `POST /api/v1/damages`.

## Inventory Engine

Per `(warehouse, sku)` we keep five buckets:

| Bucket | Meaning |
|---|---|
| `available` | Sellable on hand. |
| `reserved` | Earmarked for confirmed orders, not yet picked. |
| `in_transit` | Packed + dispatched, still owned by us. |
| `returned` | Came back from courier, awaiting inspection. |
| `damaged` | Inspected and unsellable. |

Transitions:

```
confirmed              available  → reserved
cancelled-from-confirmed reserved → available
packed                 reserved   → in_transit
delivered              in_transit → (gone)
return_pending         in_transit → returned
restockable            returned   → available
damaged                returned   → damaged
lost (in transit)      in_transit → (gone, compensation logged)
lost (returned)        returned   → (gone, compensation logged)
```

## Roles & Permissions

Sidebar items are gated by role:

- **admin** — sees everything.
- **customer_support** — Verification, Customers, Exchanges.
- **warehouse_admin** — Processing, Picking, Pack scan, Lost.
- **packer** — Processing, Pack scan, Picking.
- **dispatcher** — Dispatch, Dispatch scan, Tracking, Lost.
- **returns_clerk** — Returns, Return scan, Exchanges, Damages.
- **damage_clerk** — Damages.
- **inventory_admin** — Inventory, Stock Counts.
- **stock_counter** — Inventory, Stock Counts.

## Audit Log

`AuditLog` captures every status change, every CS edit, and every bulk
scan batch (with the ok / skipped / unknown breakdown).

## Endpoints CourierManager calls

| Slug | Adapter | Booking | Status mapping |
|---|---|---|---|
| `steadfast` | `SteadfastCourier` | POST `https://portal.packzy.com/api/v1/create_order` | Real responses → our taxonomy |
| `pathao` | `PathaoCourier` | Stub (synthesizes tracking) | OAuth-ready, mapping table built |
| `redx` | `RedxCourier` | Stub | mapping table built |
| `carrybee` | `CarryBeeCourier` | Stub | mapping table built |
| `manual` | `ManualCourier` | Generates `MAN-YYYYMMDDHHmmss-RAND` | passthrough |

Webhooks all hit `POST /api/webhooks/courier/{slug}` with the courier's
native event payload; the adapter normalizes the status before the state
machine sees it.
