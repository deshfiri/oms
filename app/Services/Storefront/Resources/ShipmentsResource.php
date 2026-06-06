<?php

namespace App\Services\Storefront\Resources;

class ShipmentsResource extends BaseResource
{
    public function list(array $filters = []): array
    {
        return $this->c->request('GET', 'shipments', [], $filters);
    }

    public function find(int|string $id): array
    {
        return $this->c->request('GET', "shipments/{$id}");
    }

    public function update(int|string $id, array $payload): array
    {
        return $this->c->request('PATCH', "shipments/{$id}", $payload);
    }
}
