<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $guarded = [];
    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];
    public function store() { return $this->belongsTo(Store::class); }
    public function stock() { return $this->hasMany(WarehouseStock::class); }
}
