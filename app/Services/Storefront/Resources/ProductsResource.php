<?php

namespace App\Services\Storefront\Resources;

class ProductsResource extends BaseResource
{
    public function list(array $filters = []): array
    {
        return $this->c->request('GET', 'products', [], $filters);
    }

    public function find(string $sku): array
    {
        return $this->c->request('GET', "products/{$sku}");
    }

    public function setStock(string $sku, int $quantity, ?string $reason = null): array
    {
        return $this->c->request('PATCH', "products/{$sku}/stock",
            ['quantity' => $quantity] + ($reason ? ['reason' => $reason] : []));
    }
}
