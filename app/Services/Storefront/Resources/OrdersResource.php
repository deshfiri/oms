<?php

namespace App\Services\Storefront\Resources;

class OrdersResource extends BaseResource
{
    public function list(array $filters = []): array
    {
        return $this->c->request('GET', 'orders', [], $filters);
    }

    /** Create an order on the storefront (OMS-placed). Returns the created order. */
    public function create(array $payload): array
    {
        return $this->c->request('POST', 'orders', $payload);
    }

    public function find(string $orderNumber): array
    {
        return $this->c->request('GET', "orders/{$orderNumber}");
    }

    public function setStatus(string $orderNumber, string $to, ?string $note = null): array
    {
        return $this->c->request('PATCH', "orders/{$orderNumber}/status",
            ['to' => $to] + ($note ? ['note' => $note] : []));
    }

    public function cancel(string $orderNumber, ?string $reason = null): array
    {
        return $this->c->request('POST', "orders/{$orderNumber}/cancel",
            $reason ? ['reason' => $reason] : []);
    }

    public function refund(string $orderNumber, array $payload): array
    {
        return $this->c->request('POST', "orders/{$orderNumber}/refund", $payload);
    }

    public function updateContact(string $orderNumber, array $payload): array
    {
        return $this->c->request('PATCH', "orders/{$orderNumber}/contact", $payload);
    }

    public function updateShippingAddress(string $orderNumber, array $payload): array
    {
        return $this->c->request('PATCH', "orders/{$orderNumber}/shipping-address", $payload);
    }

    public function addPayment(string $orderNumber, array $payload): array
    {
        return $this->c->request('POST', "orders/{$orderNumber}/payments", $payload);
    }

    public function createShipment(string $orderNumber, array $payload): array
    {
        return $this->c->request('POST', "orders/{$orderNumber}/shipments", $payload);
    }
}
