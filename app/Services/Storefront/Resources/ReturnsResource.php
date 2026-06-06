<?php

namespace App\Services\Storefront\Resources;

class ReturnsResource extends BaseResource
{
    public function list(array $filters = []): array
    {
        return $this->c->request('GET', 'returns', [], $filters);
    }

    public function find(int|string $id): array
    {
        return $this->c->request('GET', "returns/{$id}");
    }

    public function scan(string $code): array
    {
        return $this->c->request('GET', 'returns/scan', [], ['code' => $code]);
    }

    public function create(array $payload): array
    {
        return $this->c->request('POST', 'returns', $payload);
    }

    public function approve(int|string $id, array $payload = []): array
    {
        return $this->c->request('POST', "returns/{$id}/approve", $payload);
    }

    public function receive(int|string $id, array $payload = []): array
    {
        return $this->c->request('POST', "returns/{$id}/receive", $payload);
    }

    public function complete(int|string $id, array $payload = []): array
    {
        return $this->c->request('POST', "returns/{$id}/complete", $payload);
    }

    public function reject(int|string $id, array $payload = []): array
    {
        return $this->c->request('POST', "returns/{$id}/reject", $payload);
    }
}
