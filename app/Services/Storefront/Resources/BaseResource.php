<?php

namespace App\Services\Storefront\Resources;

use App\Services\Storefront\StorefrontClient;

abstract class BaseResource
{
    public function __construct(protected StorefrontClient $c) {}
}
