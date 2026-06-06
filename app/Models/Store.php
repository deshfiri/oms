<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Store extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = ['api_secret_enc', 'webhook_secret'];

    public function orders() { return $this->hasMany(OrderMirror::class); }
    public function products() { return $this->hasMany(ProductMirror::class); }
    public function customers() { return $this->hasMany(CustomerMirror::class); }
    public function shipments() { return $this->hasMany(ShipmentLog::class); }
    public function damages() { return $this->hasMany(DamageLog::class); }
    public function assignedUsers() { return $this->belongsToMany(User::class, 'user_stores')->withTimestamps(); }

    protected function apiSecret(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->api_secret_enc ? Crypt::decryptString($this->api_secret_enc) : null,
            set: fn ($value) => ['api_secret_enc' => $value ? Crypt::encryptString($value) : null],
        );
    }
}
