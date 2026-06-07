<?php

namespace App\Services\Storefront;

use App\Models\Store;
use App\Services\Storefront\Resources\CartsResource;
use App\Services\Storefront\Resources\CustomersResource;
use App\Services\Storefront\Resources\DamagesResource;
use App\Services\Storefront\Resources\InventoryResource;
use App\Services\Storefront\Resources\OrdersResource;
use App\Services\Storefront\Resources\ProductsResource;
use App\Services\Storefront\Resources\ReturnsResource;
use App\Services\Storefront\Resources\ShipmentsResource;
use App\Services\Storefront\Resources\SettingsResource;
use App\Services\Storefront\Resources\WebhooksResource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class StorefrontClient
{
    public function __construct(public Store $store) {}

    public function orders(): OrdersResource       { return new OrdersResource($this); }
    public function shipping(): \App\Services\Storefront\Resources\ShippingResource { return new \App\Services\Storefront\Resources\ShippingResource($this); }
    public function products(): ProductsResource   { return new ProductsResource($this); }
    public function inventory(): InventoryResource { return new InventoryResource($this); }
    public function shipments(): ShipmentsResource { return new ShipmentsResource($this); }
    public function returns(): ReturnsResource     { return new ReturnsResource($this); }
    public function damages(): DamagesResource     { return new DamagesResource($this); }
    public function customers(): CustomersResource { return new CustomersResource($this); }
    public function carts(): CartsResource         { return new CartsResource($this); }
    public function webhooks(): WebhooksResource   { return new WebhooksResource($this); }
    public function settings(): SettingsResource   { return new SettingsResource($this); }

    public function ping(): bool
    {
        return $this->pingDetailed()['ok'];
    }

    /**
     * Like ping() but returns the diagnostic so callers can show *why* it failed.
     *
     * @return array{ok:bool, status:?int, message:?string}
     */
    public function pingDetailed(): array
    {
        // DFCOMMERCE has no dedicated /ping — hit a cheap read endpoint instead.
        try {
            $this->request('GET', 'products', [], ['limit' => 1]);
            return ['ok' => true, 'status' => 200, 'message' => null];
        } catch (StorefrontApiException $e) {
            return ['ok' => false, 'status' => $e->status, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => null, 'message' => $e->getMessage()];
        }
    }

    public function request(string $method, string $path, array $payload = [], array $query = []): array
    {
        $url = rtrim($this->store->base_url, '/').'/api/v1/'.ltrim($path, '/');

        $request = Http::withHeaders([
                'X-API-Key'    => $this->store->api_key,
                'X-API-Secret' => $this->store->api_secret,
                'Accept'       => 'application/json',
                'User-Agent'   => 'DFOMS/1.0',
            ])
            // Short connect timeout so a slow/unreachable storefront never hangs
            // an OMS action (status pushes are fire-and-forget). Overall request
            // timeout stays generous for large reads.
            ->connectTimeout(3)
            ->timeout(15)
            ->retry(1, 250, fn ($e) => $e instanceof ConnectionException, throw: false);

        $method = strtoupper($method);
        if ($method === 'GET' || $method === 'DELETE') {
            $resp = $request->send($method, $url, ['query' => array_merge($query, $payload)]);
        } else {
            $resp = $request->send($method, $url, ['query' => $query, 'json' => $payload]);
        }

        if (! $resp->successful()) {
            $body = $resp->json();
            throw new StorefrontApiException(
                $resp->status(),
                $body['error']['message'] ?? $resp->body(),
                is_array($body) ? $body : null,
            );
        }

        return $resp->json() ?? [];
    }
}
