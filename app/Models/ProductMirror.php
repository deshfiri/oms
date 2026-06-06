<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMirror extends Model
{
    protected $table = 'products_mirror';
    protected $guarded = [];

    protected $casts = [
        'manage_stock' => 'boolean',
        'updated_at_remote' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function store() { return $this->belongsTo(Store::class); }

    public function isLowStock(): bool
    {
        return $this->manage_stock && $this->stock_quantity <= $this->low_stock_threshold;
    }
}
