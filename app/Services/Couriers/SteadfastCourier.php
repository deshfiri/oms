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
        return ['provides_consignment'=>true, 'provides_tracking'=>true, 'direct_api'=>true, 'manual_entry'=>false];
    }

    /** @return array{0:string,1:string} [api key, secret] */
    private function credentials(OrderMirror $order): array
    {
        // Per-store credentials take priority; fall back to global config.
        $storeCreds = $order->store ? $order->store->credentialsFor('steadfast') : [];
        $apiKey     = $storeCreds['api_key']    ?? config('couriers.steadfast.api_key');
        $secret     = $storeCreds['secret_key'] ?? config('couriers.steadfast.secret');

        if (! $apiKey || ! $secret) {
            throw new CourierUnavailableException(
                'Steadfast credentials not configured — add them in Stores → Edit → Courier credentials, or set STEADFAST_API_KEY / STEADFAST_SECRET in .env.'
            );
        }
        return [$apiKey, $secret];
    }

    /**
     * Native delivery status of an existing consignment (GET status_by_cid).
     * Used to verify a consignment created in the Steadfast panel (exchange
     * parcels — the API has no exchange option) before the OMS records it.
     *
     * @throws CourierUnavailableException  no credentials
     * @throws \RuntimeException            unknown consignment / API or network error
     */
    public function consignmentStatus(OrderMirror $order, string $consignmentId): string
    {
        [$apiKey, $secret] = $this->credentials($order);

        try {
            $resp = Http::withHeaders([
                    'Api-Key'    => $apiKey,
                    'Secret-Key' => $secret,
                    'Accept'     => 'application/json',
                ])
                ->timeout(15)
                ->get('https://portal.packzy.com/api/v1/status_by_cid/'.rawurlencode($consignmentId));
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not reach Steadfast to verify the consignment: '.$e->getMessage());
        }

        $body = $resp->json() ?? [];
        if (! $resp->successful() || (int) ($body['status'] ?? 0) !== 200 || empty($body['delivery_status'])) {
            throw new \RuntimeException("Steadfast did not confirm consignment {$consignmentId}: ".($body['message'] ?? 'not found on this Steadfast account').'.');
        }
        return (string) $body['delivery_status'];
    }

    public function bookConsignment(OrderMirror $order, array $opts = []): array
    {
        [$apiKey, $secret] = $this->credentials($order);

        $payload = [
            'invoice'           => $order->order_number,
            'recipient_name'    => $order->shipping_name,
            'recipient_phone'   => $order->shipping_phone,
            'recipient_address' => $order->shipping_address_line.', '.$order->shipping_area.', '.$order->shipping_city,
            'cod_amount'        => $opts['cod_amount'] ?? ($order->payment_method === 'cod' ? $order->grand_total : 0),
            // Rider instructions — Steadfast's documented `note` field (max 480 chars).
            'note'              => isset($opts['note']) && $opts['note'] !== '' ? mb_substr($opts['note'], 0, OrderMirror::COURIER_NOTE_MAX) : null,
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
            'in_review','pending'         => 'booked',
            'cancelled'                   => 'delivery_failed',
            'on_hold'                     => 'in_transit',
            'delivered'                   => 'delivered',
            'partial_delivered',
            'partially_delivered'         => 'partial_delivered',
            'unknown'                     => 'in_transit',
            'pickup_pending'              => 'booked',
            'pickup_cancelled'            => 'delivery_failed',
            'delivery_pending'            => 'out_for_delivery',
            'pickup_assigned'             => 'picked_up',
            'in_hub'                      => 'hub_received',
            'in_transit'                  => 'in_transit',
            default                       => $native,
        };
    }
}
