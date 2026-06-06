<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCount extends Model
{
    protected $guarded = [];
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function store() { return $this->belongsTo(Store::class); }
    public function lines() { return $this->hasMany(StockCountLine::class); }
}
