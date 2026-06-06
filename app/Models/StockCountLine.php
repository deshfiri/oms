<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCountLine extends Model
{
    protected $guarded = [];
    protected $casts = ['counted_at' => 'datetime'];

    public function count() { return $this->belongsTo(StockCount::class, 'stock_count_id'); }
}
