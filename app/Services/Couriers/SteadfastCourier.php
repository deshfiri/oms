<?php

namespace App\Services\Couriers;

use App\Models\OrderMirror;
use Illuminate\Support\Facades\Http;

class SteadfastCourier implements CourierAdapter
{
    public function slug(): string        { return 'steadfast'; }
    public function displayName(): string { return 'Steadfast'; }

    public function capabilities(): array
    {
        // Steadfast returns a real consignment id AND a tracking code (+URL).
        return ['provides_consignment'=>true, 'provides_tracking'=>true, 'direct_api'=>true, 'manual_entry'=>false];
    }

    public function bookConsignment(OrderMirror $order, array $opts = []): array
    {
        $apiKey    = config('couriers.steadfast.api_key');
        $secret    = config('couriers.steadfast.secret');

        if (! $apiKey || ! $secret) {
            // No keys ⇒ no real booking. We do NOT invent an id — the caller
            // creates a pending consignment instead, so nothing fake is shown.
            throw new CourierUnavailableException('Steadfast API keys not configured in OMS — book through the store website or enter the consignment manually.');
        }

        $payload = [
            'invoice'        => $order->order_number,
            'recipient_name' => $order->shipping_name,
            'recipient_phone'=> $order->shipping_phone,
            'recipient_address' => $order->shipping_address_line.', '.$order->shipping_area.', '.$order->shipping_city,
            'cod_amount'     => $opts['cod_amount'] ?? ($order->payment_method === 'cod' ? $order->grand_total : 0),
            'note'           => $opts['note'] ?? null,
        ];

        $resp = Http::withHeaders([
                'Api-Key'      => $apiKey,
                'Secret-Key'   => $secret,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
            ->timeout(15)
            ->post('https://portal.packzy.com/api/v1/create_order', $payload);

        $body = $resp->json() ?? [];
        if (! $resp->successful() || ($body['status'] ?? 0) !== 200) {
            throw new \RuntimeException('Steadfast booking failed: '.($body['message'] ?? $resp->body()));
        }

        $c = $body['consignment'] ?? [];
        return [
            'consignment_id' => $c['consignment_id'] ?? null,
            'tracking_code'  => $c['tracking_code']  ?? null,
            'tracking_url'   => isset($c['tracking_code']) ? 'https://steadfast.com.bd/t/'.$c['tracking_code'] : null,
            'label_url'      => null,
            'raw'            => $body,
        ];
    }

    public function normalizeStatus(string $native): string
    {
        return match (strtolower($native)) {
            'in_review','pending'     => 'booked',
            'cancelled'               => 'delivery_failed',
            'on_hold'                 => 'in_transit',
            'delivered'               => 'delivered',
            'partial_delivered'       => 'partial_delivered',
            'partially_delivered'     => 'partial_delivered',
            'unknown'                 => 'in_transit',
            'pickup_pending'          => 'booked',
            'pickup_cancelled'        => 'delivery_failed',
            'delivery_pending'        => 'out_for_delivery',
            'pickup_assigned'         => 'picked_up',
            'in_hub'                  => 'hub_received',
            'in_transit'              => 'in_transit',
            default                   => $native,
        };
    }
}
