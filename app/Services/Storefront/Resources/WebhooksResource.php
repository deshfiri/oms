<?php

namespace App\Services\Storefront\Resources;

class WebhooksResource extends BaseResource
{
    public function list(): array
    {
        return $this->c->request('GET', 'webhooks');
    }

    public function subscribe(string $url, array $events): array
    {
        return $this->c->request('POST', 'webhooks', ['url' => $url, 'events' => $events]);
    }

    public function delete(int|string $id): array
    {
        return $this->c->request('DELETE', "webhooks/{$id}");
    }
}
