<?php

namespace Database\Seeders;

use App\Models\OrderItemMirror;
use App\Models\OrderMirror;
use App\Models\ProductMirror;
use App\Models\Store;
use App\Models\Warehouse;
use App\Models\WarehouseBillingRate;
use App\Models\WarehouseProductCost;
use App\Models\WarehouseStock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WarehouseCostSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Stores ────────────────────────────────────────────────────
        $fashion = Store::updateOrCreate(['dfid' => 'DFTRD001'], [
            'name'            => 'trendbd',
            'business_name'   => 'TrendBD Fashion',
            'base_url'        => 'https://trendbd.example.com',
            'api_key'         => 'demo-key-fashion',
            'api_secret_enc'  => 'demo',
            'webhook_secret'  => 'demo-wh-fashion',
            'default_courier' => 'steadfast',
            'is_active'       => true,
        ]);

        $sneakers = Store::updateOrCreate(['dfid' => 'DFSNK001'], [
            'name'            => 'sneakerlab',
            'business_name'   => 'Sneaker Lab BD',
            'base_url'        => 'https://sneakerlab.example.com',
            'api_key'         => 'demo-key-sneakers',
            'api_secret_enc'  => 'demo',
            'webhook_secret'  => 'demo-wh-sneakers',
            'default_courier' => 'redx',
            'is_active'       => true,
        ]);

        // ── 2. Warehouses ─────────────────────────────────────────────────
        $wh1 = Warehouse::updateOrCreate(
            ['store_id' => $fashion->id,  'code' => 'DK-MAIN'],
            ['name' => 'Dhaka Main Warehouse', 'address' => 'Tejgaon Industrial Area, Dhaka', 'is_default' => true, 'is_active' => true]
        );

        $wh2 = Warehouse::updateOrCreate(
            ['store_id' => $sneakers->id, 'code' => 'DK-MAIN'],
            ['name' => 'Dhaka Main Warehouse', 'address' => 'Mirpur DOHS, Dhaka', 'is_default' => true, 'is_active' => true]
        );

        // ── 3. Products — TrendBD Fashion ─────────────────────────────────
        $fashionProducts = [
            // [sku, name, price, sale_price, cost, daily_rate, available, reserved, in_transit, returned, damaged, notes]
            ['TRD-TSHIRT-BLK-M',  'Classic Crew Tee — Black M',       750,  599,  280, 0.80, 45, 8, 5, 2, 1, 'bestseller'],
            ['TRD-TSHIRT-WHT-M',  'Classic Crew Tee — White M',       750,  599,  280, 0.80, 38, 6, 4, 1, 0, null],
            ['TRD-TSHIRT-NVY-L',  'Classic Crew Tee — Navy L',        750,  599,  280, 0.80, 22, 3, 0, 0, 0, null],
            ['TRD-POLO-BLK-M',    'Pique Polo — Black M',            1100,  899,  420, 1.20, 30, 5, 3, 1, 0, null],
            ['TRD-POLO-WHT-L',    'Pique Polo — White L',            1100,  899,  420, 1.20, 18, 2, 0, 0, 1, null],
            ['TRD-HOODIE-GRY-M',  'Fleece Hoodie — Grey M',          1800, 1499,  680, 2.00, 20, 4, 2, 0, 0, null],
            ['TRD-HOODIE-BLK-L',  'Fleece Hoodie — Black L',         1800, 1499,  680, 2.00, 12, 2, 1, 0, 0, null],
            ['TRD-CHINO-KHK-32',  'Slim Chino Pants — Khaki 32',     1600, 1299,  600, 1.80, 25, 3, 2, 1, 0, null],
            ['TRD-CHINO-BLK-30',  'Slim Chino Pants — Black 30',     1600, 1299,  600, 1.80, 19, 4, 0, 0, 0, null],
            ['TRD-CHINO-NVY-34',  'Slim Chino Pants — Navy 34',      1600, 1299,  600, 1.80,  8, 1, 0, 0, 0, 'low stock'],
            ['TRD-JEANS-BLK-32',  'Slim Jeans — Black 32',           2200, 1799,  850, 2.20, 16, 2, 3, 0, 0, null],
            ['TRD-JEANS-IND-30',  'Slim Jeans — Indigo 30',          2200, 1799,  850, 2.20, 14, 3, 1, 0, 0, null],
            ['TRD-JACKET-BLK-M',  'Bomber Jacket — Black M',         3500, 2999, 1350, 3.50, 10, 2, 1, 0, 0, null],
            ['TRD-JACKET-OLV-L',  'Bomber Jacket — Olive L',         3500, 2999, 1350, 3.50,  7, 1, 0, 0, 0, null],
            ['TRD-SHIRT-BLU-M',   'Oxford Shirt — Blue M',           1400, 1099,  520, 1.50, 22, 4, 2, 1, 0, null],
            ['TRD-SHIRT-WHT-L',   'Oxford Shirt — White L',          1400, 1099,  520, 1.50, 18, 3, 0, 0, 0, null],
            ['TRD-SHORT-BLK-M',   'Jogger Shorts — Black M',          799,  649,  300, 0.70, 35, 5, 4, 0, 0, null],
            ['TRD-SHORT-GRY-L',   'Jogger Shorts — Grey L',           799,  649,  300, 0.70, 28, 3, 2, 0, 0, null],
            ['TRD-TRACK-NVY-M',   'Tracksuit Set — Navy M',          2800, 2299, 1050, 2.80, 12, 2, 1, 0, 0, null],
            ['TRD-SWEAT-GRY-M',   'Sweatshirt — Grey M',             1300, 1099,  490, 1.40, 20, 3, 2, 0, 1, null],
            ['TRD-CAP-BLK',       'Structured Cap — Black',           599,  499,  200, 0.50, 50, 8, 5, 2, 0, 'accessories'],
            ['TRD-CAP-WHT',       'Structured Cap — White',           599,  499,  200, 0.50, 40, 6, 3, 1, 0, null],
            ['TRD-BELT-BRN-32',   'Leather Belt — Brown 32"',         799,  649,  280, 0.60, 30, 4, 2, 0, 0, null],
            ['TRD-SOCKS-WHT-3PK', 'Ankle Socks White 3-Pack',         299,  249,   90, 0.20, 80, 10, 8, 3, 0, null],
            ['TRD-SOCKS-BLK-3PK', 'Ankle Socks Black 3-Pack',         299,  249,   90, 0.20, 72, 9, 6, 2, 0, null],
        ];

        $this->seedStoreProducts($fashion, $wh1, $fashionProducts);

        // ── 4. Products — Sneaker Lab BD ──────────────────────────────────
        $sneakerProducts = [
            ['SNK-AIR-WHT-42',  'AirStep Runner — White EU42',   3999, 3299, 1600, 4.00, 18, 3, 2, 1, 0, 'bestseller'],
            ['SNK-AIR-BLK-43',  'AirStep Runner — Black EU43',   3999, 3299, 1600, 4.00, 14, 2, 1, 0, 0, null],
            ['SNK-AIR-GRY-41',  'AirStep Runner — Grey EU41',    3999, 3299, 1600, 4.00, 10, 1, 0, 0, 0, null],
            ['SNK-CLASSIC-WHT-42', 'Classic Low — White EU42',   2799, 2299, 1100, 3.00, 22, 4, 3, 1, 0, null],
            ['SNK-CLASSIC-BLK-43', 'Classic Low — Black EU43',   2799, 2299, 1100, 3.00, 16, 2, 2, 0, 0, null],
            ['SNK-CLASSIC-NVY-41', 'Classic Low — Navy EU41',    2799, 2299, 1100, 3.00, 12, 2, 0, 0, 1, null],
            ['SNK-TRAIL-OLV-42', 'TrailBlazer Hiker — Olive EU42', 4999, 4299, 1900, 5.00, 8,  1, 1, 0, 0, null],
            ['SNK-TRAIL-BRN-43', 'TrailBlazer Hiker — Brown EU43', 4999, 4299, 1900, 5.00, 6,  1, 0, 0, 0, null],
            ['SNK-SLIP-BLK-42',  'Slip-On Canvas — Black EU42',  1799, 1499,  700, 2.00, 24, 4, 3, 1, 0, null],
            ['SNK-SLIP-WHT-41',  'Slip-On Canvas — White EU41',  1799, 1499,  700, 2.00, 20, 3, 2, 0, 0, null],
            ['SNK-SANDAL-BRN-42','Leather Sandal — Brown EU42',  1999, 1699,  780, 2.20, 14, 2, 1, 0, 0, 'summer'],
            ['SNK-SANDAL-BLK-43','Leather Sandal — Black EU43',  1999, 1699,  780, 2.20, 10, 1, 0, 0, 0, null],
            ['SNK-BOOT-BRN-42',  'Chelsea Boot — Brown EU42',    5500, 4799, 2100, 6.00,  6, 1, 1, 0, 0, null],
            ['SNK-BOOT-BLK-43',  'Chelsea Boot — Black EU43',    5500, 4799, 2100, 6.00,  4, 1, 0, 0, 0, null],
            ['SNK-LACES-WHT',    'Flat Laces White 120cm',        199,  149,   55, 0.15, 120,15, 10, 5, 0, 'accessories'],
            ['SNK-LACES-BLK',    'Flat Laces Black 120cm',        199,  149,   55, 0.15, 100,12, 8,  3, 0, null],
            ['SNK-CLEAN-KIT',    'Sneaker Cleaning Kit',          699,  549,  250, 0.80, 35, 5, 4, 1, 0, 'care'],
            ['SNK-SOLE-GEL-42',  'Gel Insole EU42-44',            499,  399,  170, 0.40, 40, 6, 5, 0, 0, null],
            ['SNK-BAG-DUST',     'Dust Bag for Sneakers',         299,  249,  100, 0.30, 60, 8, 5, 2, 0, null],
            ['SNK-SOCKS-LOW-3PK','Low-Cut Sports Socks 3-Pack',   349,  299,  110, 0.25, 75,10, 7, 3, 0, null],
        ];

        $this->seedStoreProducts($sneakers, $wh2, $sneakerProducts);

        // ── 5. Billing rates ──────────────────────────────────────────────
        // Global default
        WarehouseBillingRate::updateOrCreate(['store_id' => null], [
            'storage_rate_per_item_per_month' => 5.00,
            'packaging_cost_per_item'         => 8.00,
        ]);
        // Per-store override for Sneaker Lab (higher storage, bulkier items)
        WarehouseBillingRate::updateOrCreate(['store_id' => $sneakers->id], [
            'storage_rate_per_item_per_month' => 8.00,
            'packaging_cost_per_item'         => 12.00,
        ]);

        // ── 6. Dummy packed orders this month (for packaging cost data) ───
        $this->seedPackedOrders($fashion,  $fashionProducts);
        $this->seedPackedOrders($sneakers, $sneakerProducts);
    }

    /**
     * Create fake packed orders this month so packaging cost shows real numbers.
     * Picks random products from the store's catalogue.
     */
    private function seedPackedOrders(Store $store, array $products): void
    {
        $customers = [
            ['Arif Hossain',   '01711223344', 'House 12, Road 5, Dhanmondi', 'Dhanmondi', 'Dhaka'],
            ['Nasrin Akter',   '01811334455', 'Flat 3B, Green Tower, Mirpur','Mirpur',    'Dhaka'],
            ['Raihan Ahmed',   '01912445566', 'Plot 7, Section 10, Uttara',  'Uttara',    'Dhaka'],
            ['Sadia Islam',    '01611556677', '45 Kazi Nazrul Ave, Khulna',  'Khulna',    'Khulna'],
            ['Farhan Kabir',   '01511667788', '8 Agrabad C/A, Chittagong',   'Agrabad',   'Chittagong'],
            ['Mitu Begum',     '01411778899', '22 Shahjalal Rd, Sylhet',     'Sylhet',    'Sylhet'],
            ['Tanvir Hassan',  '01311889900', '99 Jail Rd, Comilla',         'Comilla',   'Comilla'],
            ['Ripa Chowdhury', '01211990011', '15 New Market, Rajshahi',     'Rajshahi',  'Rajshahi'],
        ];

        $orderCount = 18; // packed orders this month per store
        $now = Carbon::now();

        for ($n = 0; $n < $orderCount; $n++) {
            $customer  = $customers[$n % count($customers)];
            $packedAt  = $now->copy()->subDays(rand(0, 27))->subHours(rand(0, 8));
            $orderNum  = strtoupper(substr($store->dfid ?? 'ORD', 0, 3))
                         . '-' . $now->format('Ym')
                         . '-' . str_pad($n + 1, 4, '0', STR_PAD_LEFT);

            // 1-3 random products per order
            $lineCount = rand(1, 3);
            $picked = array_slice($products, ($n * $lineCount) % max(1, count($products) - $lineCount), $lineCount);

            $subtotal = 0;
            $lines = [];
            foreach ($picked as [$sku, $name, $price, $salePrice]) {
                $qty       = rand(1, 2);
                $unitPrice = $salePrice ?? $price;
                $subtotal += $qty * $unitPrice;
                $lines[]  = compact('sku', 'name', 'qty', 'unitPrice');
            }

            $shipping  = in_array($customer[4], ['Dhaka']) ? 70 : 130;
            $grandTotal = $subtotal + $shipping;

            $order = OrderMirror::updateOrCreate(
                ['store_id' => $store->id, 'order_number' => $orderNum],
                [
                    'status'               => 'packed',
                    'payment_method'       => ($n % 3 === 0) ? 'bkash' : 'cod',
                    'customer_name'        => $customer[0],
                    'customer_phone'       => $customer[1],
                    'shipping_name'        => $customer[0],
                    'shipping_phone'       => $customer[1],
                    'shipping_address_line'=> $customer[2],
                    'shipping_area'        => $customer[3],
                    'shipping_city'        => $customer[4],
                    'subtotal'             => $subtotal,
                    'shipping_total'       => $shipping,
                    'grand_total'          => $grandTotal,
                    'placed_at'            => $packedAt->copy()->subHours(rand(6, 48)),
                    'confirmed_at'         => $packedAt->copy()->subHours(rand(3, 6)),
                    'processing_at'        => $packedAt->copy()->subHours(rand(1, 3)),
                    'packed_at'            => $packedAt,
                ]
            );

            // Delete old items and re-insert to avoid duplicates
            OrderItemMirror::where('order_id', $order->id)->delete();
            foreach ($lines as $line) {
                OrderItemMirror::create([
                    'order_id'   => $order->id,
                    'sku'        => $line['sku'],
                    'name'       => $line['name'],
                    'qty'        => $line['qty'],
                    'unit_price' => $line['unitPrice'],
                    'line_total' => $line['qty'] * $line['unitPrice'],
                ]);
            }
        }
    }

    /**
     * Insert products, warehouse stock, and cost records for one store.
     *
     * @param array $rows  Each row: [sku, name, price, sale_price, cost_price, daily_rate,
     *                                available, reserved, in_transit, returned, damaged, notes]
     */
    private function seedStoreProducts(Store $store, Warehouse $warehouse, array $rows): void
    {
        foreach ($rows as $i => [$sku, $name, $price, $salePrice, $costPrice, $dailyRate,
                                  $available, $reserved, $inTransit, $returned, $damaged, $notes]) {
            $totalQty = $available + $reserved + $inTransit + $returned;

            ProductMirror::updateOrCreate(
                ['store_id' => $store->id, 'sku' => $sku],
                [
                    'product_id'     => $store->id * 1000 + $i + 1,
                    'name'           => $name,
                    'type'           => 'simple',
                    'price'          => $price,
                    'sale_price'     => $salePrice,
                    'stock_quantity' => $available,
                    'stock_status'   => $available > 0 ? 'in_stock' : 'out_of_stock',
                    'manage_stock'   => true,
                    'low_stock_threshold' => 5,
                    'bin_location'   => $this->binLocation($i),
                    'last_synced_at' => now()->subMinutes(rand(5, 180)),
                ]
            );

            WarehouseStock::updateOrCreate(
                ['warehouse_id' => $warehouse->id, 'sku' => $sku],
                [
                    'available'  => $available,
                    'reserved'   => $reserved,
                    'in_transit' => $inTransit,
                    'returned'   => $returned,
                    'damaged'    => $damaged,
                ]
            );

            WarehouseProductCost::updateOrCreate(
                ['store_id' => $store->id, 'sku' => $sku],
                [
                    'cost_price'         => $costPrice,
                    'daily_storage_rate' => $dailyRate,
                    'notes'              => $notes,
                ]
            );
        }
    }

    private function binLocation(int $i): string
    {
        $aisles = ['A', 'B', 'C', 'D'];
        $aisle  = $aisles[$i % count($aisles)];
        $shelf  = intdiv($i, count($aisles)) + 1;
        $slot   = ($i % 4) + 1;
        return "{$aisle}{$shelf}-{$slot}";
    }
}
