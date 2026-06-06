<?php

namespace App\Services\Storefront\Resources;

class CartsResource extends BaseResource
{
    public function abandoned(array $filters = []): array
    {
        return $this->c->request('GET', 'abandoned-carts', [], $filters);
    }

    public function incompleteOrders(array $filters = []): array
    {
        return $this->c->request('GET', 'incomplete-orders', [], $filters);
    }
}
