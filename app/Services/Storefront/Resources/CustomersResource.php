<?php

namespace App\Services\Storefront\Resources;

class CustomersResource extends BaseResource
{
    public function list(array $filters = []): array
    {
        return $this->c->request('GET', 'customers', [], $filters);
    }

    public function find(int|string $id): array
    {
        return $this->c->request('GET', "customers/{$id}");
    }
}
