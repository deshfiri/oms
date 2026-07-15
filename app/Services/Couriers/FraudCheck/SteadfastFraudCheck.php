<?php

namespace App\Services\Couriers\FraudCheck;

use App\Models\Store;
use App\Services\Storefront\StorefrontApiException;
use App\Services\Storefront\StorefrontClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Steadfast delivery-history check.
 *
 * Two credential paths, tried in this order:
 *  1. DIRECT — OMS-side keys (per-store courier credentials or .env), same
 *     pair the SteadfastCourier booking adapter uses.
 *  2. STOREFRONT PROXY — when the OMS holds no keys, ask the store website
 *     (GET /api/v1/couriers/fraud-check) to run the check with ITS configured
 *     Steadfast credentials. Keys never leave the storefront.
 *
 * Endpoint (both paths): GET portal.packzy.com/api/v1/fraud_check/{phone}.
 * Response bodies are parsed defensively; numbers are never fabricated.
 */
class SteadfastFraudCheck implements CourierFraudCheckInterface
{
    public const BASE_URL = 'https://portal.packzy.com/api/v1';

    public function check(Store $store, string $phone): array
    {
        // Same credential resolution as SteadfastCourier::bookConsignment().
        $creds  = $store->credentialsFor('steadfast');
        $apiKey = $creds['api_key']    ?? config('couriers.steadfast.api_key');
        $secret = $creds['secret_key'] ?? config('couriers.steadfast.secret');

        if (! $apiKey || ! $secret) {
            // The store website usually holds the courier keys in this
            // ecosystem — proxy the check through it.
            return $this->viaStorefront($store, $phone);
        }

        return $this->direct($store, $phone, $apiKey, $secret);
    }

    /** Path 1 — OMS calls Steadfast itself. */
    private function direct(Store $store, string $phone, string $apiKey, string $secret): array
    {
        try {
            $resp = Http::withHeaders([
                    'Api-Key'    => $apiKey,
                    'Secret-Key' => $secret,
                    'Accept'     => 'application/json',
                ])
                ->connectTimeout(3)
                ->timeout(12)
                ->get(self::BASE_URL.'/fraud_check/'.$phone);
        } catch (ConnectionException $e) {
            Log::warning('[FraudCheck] steadfast connection failed', ['store' => $store->id, 'err' => $e->getMessage()]);
            throw new FraudCheckException('The courier service is currently unavailable. Please try again.', 'timeout');
        }

        if ($resp->status() === 401 || $resp->status() === 403) {
            throw new FraudCheckException(
                "The selected store's courier integration is not configured properly.",
                'not_configured',
            );
        }
        if ($resp->status() === 404) {
            // Endpoint absent on this account/API revision.
            throw new FraudCheckException('Fraud checking is not supported by this courier.', 'unsupported');
        }
        if ($resp->status() === 429) {
            throw new FraudCheckException('The courier is rate-limiting requests. Please wait a moment and try again.', 'courier_error');
        }
        if (! $resp->successful()) {
            Log::warning('[FraudCheck] steadfast non-2xx', ['store' => $store->id, 'status' => $resp->status(), 'body' => mb_substr($resp->body(), 0, 500)]);
            throw new FraudCheckException('The courier service is currently unavailable. Please try again.', 'courier_error');
        }

        $body = $resp->json();
        if (! is_array($body)) {
            Log::warning('[FraudCheck] steadfast non-JSON body', ['store' => $store->id, 'body' => mb_substr($resp->body(), 0, 500)]);
            throw new FraudCheckException('The courier returned an unexpected response.', 'invalid_response');
        }

        return $this->parseBody($store, $body);
    }

    /** Path 2 — the store website runs the check with its own credentials. */
    private function viaStorefront(Store $store, string $phone): array
    {
        try {
            $resp = (new StorefrontClient($store))->request('GET', 'couriers/fraud-check', [], [
                'phone'   => $phone,
                'courier' => 'steadfast',
            ]);
        } catch (StorefrontApiException $e) {
            if ($e->status === 404) {
                // Storefront build without the proxy endpoint.
                throw new FraudCheckException(
                    'The store website does not support fraud checking yet — update the store website.',
                    'unsupported',
                );
            }
            Log::warning('[FraudCheck] storefront proxy failed', ['store' => $store->id, 'status' => $e->status, 'err' => $e->getMessage()]);
            throw new FraudCheckException('The courier service is currently unavailable. Please try again.', 'courier_error');
        } catch (\Throwable $e) {
            Log::warning('[FraudCheck] storefront proxy unreachable', ['store' => $store->id, 'err' => $e->getMessage()]);
            throw new FraudCheckException('The courier service is currently unavailable. Please try again.', 'timeout');
        }

        $data = $resp['data'] ?? [];
        if (($data['configured'] ?? null) === false) {
            throw new FraudCheckException(
                "The selected store's courier integration is not configured properly.",
                'not_configured',
            );
        }
        if (($data['supported'] ?? null) === false) {
            throw new FraudCheckException('Fraud checking is not supported by this courier.', 'unsupported');
        }
        if (! is_array($data['raw'] ?? null)) {
            Log::warning('[FraudCheck] storefront proxy odd payload', ['store' => $store->id, 'keys' => array_keys($data)]);
            throw new FraudCheckException('The courier returned an unexpected response.', 'invalid_response');
        }

        return $this->parseBody($store, $data['raw']);
    }

    /** Normalize a raw Steadfast fraud_check body (field names vary across API revisions). */
    private function parseBody(Store $store, array $body): array
    {
        // Counts may sit at the root, under `data`, or under `fraud`.
        $node = null;
        foreach ([$body['data'] ?? null, $body['fraud'] ?? null, $body] as $candidate) {
            if (is_array($candidate) && $this->pick($candidate, ['total_delivered', 'delivered', 'success_parcel', 'successful_delivery']) !== null) {
                $node = $candidate;
                break;
            }
        }
        if ($node === null) {
            Log::warning('[FraudCheck] steadfast unrecognized shape', ['store' => $store->id, 'keys' => array_keys($body)]);
            throw new FraudCheckException('The courier returned an unexpected response.', 'invalid_response');
        }

        $delivered = (int) $this->pick($node, ['total_delivered', 'delivered', 'success_parcel', 'successful_delivery']);
        $cancelled = (int) ($this->pick($node, ['total_cancelled', 'cancelled', 'cancelled_parcel', 'total_cancel']) ?? 0);
        $returned  = (int) ($this->pick($node, ['total_returned', 'returned', 'return_parcel']) ?? 0);
        $pending   = (int) ($this->pick($node, ['total_pending', 'pending']) ?? 0);
        $total     = (int) ($this->pick($node, ['total_parcels', 'total', 'total_parcel'])
                     ?? ($delivered + $cancelled + $returned + $pending));

        return [
            'summary' => [
                'total'        => $total,
                'delivered'    => $delivered,
                'returned'     => $returned,
                'cancelled'    => $cancelled,
                'pending'      => $pending,
                'success_rate' => $total > 0 ? round($delivered / $total * 100, 1) : null,
                'return_rate'  => $total > 0 ? round(($returned + $cancelled) / $total * 100, 1) : null,
            ],
            // Steadfast returns raw counts only — risk is computed by FraudCheckService.
            'risk'          => null,
            'raw_reference' => $body['message'] ?? null,
        ];
    }

    /** First present-and-numeric value among $keys, else null. */
    private function pick(array $node, array $keys): int|float|string|null
    {
        foreach ($keys as $k) {
            if (isset($node[$k]) && is_numeric($node[$k])) return $node[$k];
        }
        return null;
    }
}
