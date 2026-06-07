<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Store extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active'       => 'boolean',
        'courier_enabled' => 'boolean',
        'last_sync_at'    => 'datetime',
    ];

    protected $hidden = ['api_secret_enc', 'webhook_secret', 'courier_credentials_enc'];

    public function orders()       { return $this->hasMany(OrderMirror::class); }
    public function products()     { return $this->hasMany(ProductMirror::class); }
    public function customers()    { return $this->hasMany(CustomerMirror::class); }
    public function shipments()    { return $this->hasMany(ShipmentLog::class); }
    public function damages()      { return $this->hasMany(DamageLog::class); }
    public function assignedUsers(){ return $this->belongsToMany(User::class, 'user_stores')->withTimestamps(); }

    protected function apiSecret(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->api_secret_enc ? Crypt::decryptString($this->api_secret_enc) : null,
            set: fn ($value) => ['api_secret_enc' => $value ? Crypt::encryptString($value) : null],
        );
    }

    /**
     * Per-store courier credentials stored as encrypted JSON.
     * Shape: ['steadfast' => ['api_key' => '...', 'secret_key' => '...'], ...]
     */
    protected function courierCredentials(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->courier_credentials_enc
                ? (json_decode(Crypt::decryptString($this->courier_credentials_enc), true) ?? [])
                : [],
            set: fn ($value) => [
                'courier_credentials_enc' => $value
                    ? Crypt::encryptString(json_encode($value))
                    : null,
            ],
        );
    }

    /** Return stored credentials for a specific courier slug. */
    public function credentialsFor(string $slug): array
    {
        return $this->courier_credentials[$slug] ?? [];
    }
}
