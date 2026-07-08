<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseBillingRate extends Model
{
    protected $table   = 'warehouse_billing_rates';
    protected $guarded = [];

    protected $casts = [
        'storage_rate_per_item_per_month' => 'decimal:2',
        'packaging_cost_per_item'         => 'decimal:2',
    ];

    public function store() { return $this->belongsTo(Store::class); }
}
