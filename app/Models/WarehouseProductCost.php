<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseProductCost extends Model
{
    protected $table  = 'warehouse_product_costs';
    protected $guarded = [];

    protected $casts = [
        'cost_price'         => 'decimal:2',
        'daily_storage_rate' => 'decimal:4',
    ];

    public function store() { return $this->belongsTo(Store::class); }
}
