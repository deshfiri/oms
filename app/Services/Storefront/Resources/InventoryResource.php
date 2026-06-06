<?php

namespace App\Services\Storefront\Resources;

class InventoryResource extends BaseResource
{
    public function list(array $filters = []): array
    {
        return $this->c->request('GET', 'inventory', [], $filters);
    }

    public function bulkSet(array $items): array
    {
        return $this->c->request('POST', 'inventory/bulk-set', ['items' => $items]);
    }
}
