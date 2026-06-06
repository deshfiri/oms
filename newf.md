Build a complete Amazon-style Ecommerce Order Management, Warehouse Management, Return Management, Exchange Management, Inventory Management, and Courier Management workflow.

When a customer places an order, it should be created with the status **Pending Verification**. Customer Support agents will contact the customer by phone to verify the order. During verification, agents must be able to confirm or cancel the order, modify customer information (name, phone number, address, delivery area), add or remove products, change quantities, apply discounts, apply coupons, modify shipping charges, and add notes. If confirmed, the order status becomes **Confirmed**. If cancelled, the order status becomes **Cancelled** with a stored cancellation reason.

Confirmed orders automatically move to the warehouse processing queue with the status **Processing**. Warehouse staff can view orders, generate pick lists, assign packing staff, and process orders individually or in bulk.

When packing begins, the system must automatically create a courier consignment through the integrated courier API and retrieve the courier name, consignment ID, tracking number, and tracking URL.

Generate a 2x3 inch thermal shipping label containing the company logo, company name, DFID, order ID, barcode, QR code, courier logo, courier name, consignment ID, tracking number, customer information, and product summary. Support both single and bulk label generation and printing.

Warehouse staff must be able to scan order barcodes and mark orders as packed individually or in bulk. After successful packing, the order status becomes **Packed**.

When products are handed over to the courier, the status becomes **Dispatched**. Once accepted by the courier, the status becomes **Shipped**.

The system must automatically synchronize courier tracking statuses via APIs and webhooks, including Picked Up, In Transit, Hub Received, Out for Delivery, Delivered, Delivery Failed, Return Initiated, Return in Transit, and Returned.

When delivery is completed, the order status becomes **Delivered** automatically. Store delivery date, time, and courier confirmation details.

If delivery fails or the customer refuses delivery, the order status becomes **Return Pending**. Store return reasons such as Customer Refused, Customer Unreachable, Wrong Address, Courier Issue, or Delivery Failure.

When returned products arrive at the warehouse, warehouse staff scan the barcode and mark the order as **Returned**. Returned products must be inspected. If the product is in good condition, automatically restock inventory and mark it as **Restockable**. If damaged, mark it as **Damaged**, store damage details, images, and notes, and prevent inventory restocking.

The system must support product exchanges before or after delivery. Exchange requests should create a linked replacement order with its own lifecycle while maintaining a relationship with the original order. The original order should remain in an **Awaiting Return Product** state until the returned item is received and inspected. Returned exchange products should either be restocked or marked as damaged.

Support lost product management for warehouse losses, courier losses, and vendor losses. Lost products should be marked as **Lost**, inventory adjusted automatically, and compensation records maintained.

Support manual and automatic damage reporting. Damage records should include quantity, reason, notes, images, responsible party, and inventory adjustments.

Inventory must update automatically for order confirmation, cancellation, packing, delivery, return, restocking, exchange, damage, and lost product events. Support multi-warehouse inventory, available stock, reserved stock, damaged stock, returned stock, and in-transit stock.

Required order statuses:

Pending Verification,
Confirmed,
Cancelled,
Processing,
Packed,
Dispatched,
Shipped,
Out For Delivery,
Delivered,
Return Pending,
Returned,
Restockable,
Damaged,
Exchange Requested,
Exchange Processing,
Awaiting Return Product,
Lost.

Support barcode scanning, QR code generation, courier API integrations, bulk processing, bulk printing, audit logs, warehouse scanning workflows, real-time tracking synchronization, role-based permissions, and complete order lifecycle management similar to Amazon, Daraz, Shopify Fulfillment Network, and enterprise 3PL fulfillment operations.
