<?php

namespace App\Services\Storefront\Resources;

class DamagesResource extends BaseResource
{
    public function list(array $filters = []): array
    {
        return $this->c->request('GET', 'damages', [], $filters);
    }

    public function find(int|string $id): array
    {
        return $this->c->request('GET', "damages/{$id}");
    }

    public function create(array $payload): array
    {
        return $this->c->request('POST', 'damages', $payload);
    }
}
