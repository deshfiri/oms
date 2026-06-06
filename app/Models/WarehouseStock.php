<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseStock extends Model
{
    protected $table = 'warehouse_stock';
    protected $guarded = [];
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
}
