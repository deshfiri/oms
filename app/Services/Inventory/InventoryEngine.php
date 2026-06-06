<?php

namespace App\Services\Inventory;

use App\Models\OrderMirror;
use App\Models\Store;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Orders\OrderStateMachine as S;
use Illuminate\Support\Facades\DB;

/**
 * Multi-warehouse stock engine. Keeps five buckets per (warehouse, sku):
 *
 *   available  : sellable on hand
 *   reserved   : earmarked for confirmed orders, not yet picked
 *   in_transit : packed + dispatched, still owned by us
 *   returned   : came back from courier, awaiting inspection
 *   damaged    : inspected and unsellable
 *
 * Bucket moves per status transition:
 *
 *   confirmed              available  → reserved
 *   cancelled (from confirmed/processing)  reserved → available
 *   packed                 reserved   → in_transit
 *   delivered              in_transit → (gone)
 *   return_pending         in_transit → returned (provisional)
 *   returned (arrived)     no-op (already moved on return_pending)
 *   restockable            returned   → available
 *   damaged                returned   → damaged
 *   lost                   any        → (gone, compensation recorded)
 */
class InventoryEngine
{
    public function onTransition(OrderMirror $order, string $from, string $to, array $meta = []): void
    {
        $store = $order->store;
        if (! $store) return;
        $warehouse = $this->defaultWarehouseFor($store);

        DB::transaction(function () use ($order, $from, $to, $warehouse) {
            foreach ($order->items as $item) {
                $sku = $item->sku;
                if (! $sku) continue;
                $qty = max(1, (int) $item->qty);
                $row = $this->row($warehouse, $sku);

                match ($to) {
                    S::CONFIRMED   => $this->move($row, 'available', 'reserved', $qty),
                    S::CANCELLED   => match ($from) {
                        S::CONFIRMED, S::PROCESSING => $this->move($row, 'reserved', 'available', $qty),
                        S::PACKED                   => $this->move($row, 'in_transit', 'available', $qty),
                        default                     => null,
                    },
                    S::PACKED      => $this->move($row, 'reserved', 'in_transit', $qty),
                    S::DELIVERED   => $this->subtract($row, 'in_transit', $qty),
                    S::RETURN_PENDING => $this->move($row, 'in_transit', 'returned', $qty),
                    S::RESTOCKABLE => $this->move($row, 'returned', 'available', $qty),
                    S::DAMAGED     => $this->move($row, 'returned', 'damaged', $qty),
                    S::LOST        => match ($from) {
                        S::DISPATCHED, S::SHIPPED, S::OUT_FOR_DELIVERY => $this->subtract($row, 'in_transit', $qty),
                        S::RETURN_PENDING, S::AWAITING_RETURN_PRODUCT  => $this->subtract($row, 'returned', $qty),
                        default                                        => null,
                    },
                    default => null,
                };
            }
        });
    }

    protected function row(Warehouse $w, string $sku): WarehouseStock
    {
        return WarehouseStock::firstOrCreate(
            ['warehouse_id' => $w->id, 'sku' => $sku],
            ['available' => 0, 'reserved' => 0, 'in_transit' => 0, 'returned' => 0, 'damaged' => 0],
        );
    }

    protected function move(WarehouseStock $row, string $from, string $to, int $qty): void
    {
        $current = (int) $row->{$from};
        $take    = min($qty, $current);
        if ($take > 0) {
            $row->{$from} = $current - $take;
            $row->{$to}   = (int) $row->{$to} + $take;
            $row->save();
        }
    }

    protected function subtract(WarehouseStock $row, string $bucket, int $qty): void
    {
        $row->{$bucket} = max(0, (int) $row->{$bucket} - $qty);
        $row->save();
    }

    public function defaultWarehouseFor(Store $store): Warehouse
    {
        $w = Warehouse::where('store_id', $store->id)->where('is_default', true)->first()
            ?? Warehouse::where('store_id', $store->id)->orderBy('id')->first();
        if (! $w) {
            $w = Warehouse::create([
                'store_id'   => $store->id,
                'code'       => 'MAIN',
                'name'       => 'Main warehouse',
                'is_default' => true,
                'is_active'  => true,
            ]);
        }
        return $w;
    }

    /** Public read of the bucket row (creates with zeros if missing). */
    public function stockFor(Warehouse $w, string $sku): WarehouseStock
    {
        return $this->row($w, $sku);
    }

    /** Public: directly bump available (e.g. after an in-house purchase). */
    public function addAvailable(Warehouse $w, string $sku, int $qty): void
    {
        $row = $this->row($w, $sku);
        $row->available = (int) $row->available + $qty;
        $row->save();
    }

    /** Public: write off due to damage report (qty out of available). */
    public function damageFromAvailable(Warehouse $w, string $sku, int $qty): void
    {
        $row = $this->row($w, $sku);
        $take = min($qty, (int) $row->available);
        if ($take > 0) {
            $row->available = (int) $row->available - $take;
            $row->damaged   = (int) $row->damaged + $take;
            $row->save();
        }
    }
}
