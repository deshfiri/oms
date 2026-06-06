<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerMirror extends Model
{
    protected $table = 'customers_mirror';
    protected $guarded = [];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function store() { return $this->belongsTo(Store::class); }
}
