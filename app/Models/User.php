<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN                = 'admin';
    public const ROLE_CUSTOMER_SUPPORT     = 'customer_support';
    public const ROLE_WAREHOUSE_ADMIN      = 'warehouse_admin';
    public const ROLE_PICKER               = 'picker';
    public const ROLE_PACKER               = 'packer';
    public const ROLE_DISPATCHER           = 'dispatcher';
    public const ROLE_RETURNS              = 'returns_clerk';
    public const ROLE_DAMAGE               = 'damage_clerk';
    public const ROLE_INVENTORY_ADMIN      = 'inventory_admin';
    public const ROLE_STOCK                = 'stock_counter';
    public const ROLE_SOCIAL_MEDIA_MANAGER = 'social_media_manager';

    public const ROLES = [
        self::ROLE_ADMIN, self::ROLE_CUSTOMER_SUPPORT, self::ROLE_WAREHOUSE_ADMIN,
        self::ROLE_PICKER, self::ROLE_PACKER, self::ROLE_DISPATCHER,
        self::ROLE_RETURNS, self::ROLE_DAMAGE, self::ROLE_INVENTORY_ADMIN,
        self::ROLE_STOCK, self::ROLE_SOCIAL_MEDIA_MANAGER,
    ];

    /** Stores this user is assigned to (used by social media managers to create orders). */
    public function stores()
    {
        return $this->belongsToMany(\App\Models\Store::class, 'user_stores')->withTimestamps();
    }

    public function canAccessStore(int $storeId): bool
    {
        if ($this->isAdmin()) return true;
        return $this->stores()->where('stores.id', $storeId)->exists();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool { return $this->role === self::ROLE_ADMIN; }
    public function hasRole(string ...$roles): bool { return in_array($this->role, $roles, true); }
}
