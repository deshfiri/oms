<?php

namespace App\Services\Couriers;

class CourierManager
{
    /** @var array<string, class-string<CourierAdapter>> */
    public const ADAPTERS = [
        'steadfast' => SteadfastCourier::class,
        'pathao'    => PathaoCourier::class,
        'redx'      => RedxCourier::class,
        'carrybee'  => CarryBeeCourier::class,
        'manual'    => ManualCourier::class,
    ];

    public function adapter(string $slug): CourierAdapter
    {
        $class = self::ADAPTERS[$slug] ?? null;
        if (! $class) throw new \InvalidArgumentException("Unknown courier: $slug");
        return app($class);
    }

    public function all(): array
    {
        return array_map(fn ($c) => app($c), self::ADAPTERS);
    }
}
