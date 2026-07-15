<?php

namespace App\Services\Couriers\FraudCheck;

use App\Models\Store;
use App\Services\Couriers\CourierConfigException;
use App\Services\Couriers\CourierManager;
use App\Services\Couriers\CourierPicker;

/**
 * Orchestrates a courier fraud check for a store + phone number:
 * resolve the store's default courier (from its storefront settings, same
 * source CourierPicker uses for booking), pick the matching checker, and
 * normalize the result into one shape every courier shares.
 *
 * The check is informational only — it never touches orders, stock, or
 * customers.
 */
class FraudCheckService
{
    /**
     * Couriers with a working fraud-check integration. Every other slug is
     * reported as unsupported — no invented endpoints.
     *
     * @var array<string, class-string<CourierFraudCheckInterface>>
     */
    public const CHECKERS = [
        'steadfast' => SteadfastFraudCheck::class,
    ];

    public function __construct(private CourierPicker $picker) {}

    /**
     * @return array{courier_slug:string,courier:string,phone:string,summary:array,risk:array,raw_reference:mixed}
     *
     * @throws FraudCheckException          operator-safe failure
     * @throws \InvalidArgumentException    invalid phone (caller validates first normally)
     */
    public function checkForStore(Store $store, string $rawPhone): array
    {
        $phone = self::normalizePhone($rawPhone);
        if ($phone === null) {
            throw new \InvalidArgumentException('Enter a valid Bangladeshi mobile number (e.g. 01712345678).');
        }

        try {
            $slug = $this->picker->defaultSlugForStore($store);
        } catch (CourierConfigException $e) {
            // Storefront unreachable / settings endpoint missing — keep the
            // operator message short; detail is already logged by the picker.
            throw new FraudCheckException(
                'Could not read the courier configuration from the store website. Please check the store connection.',
                'no_courier',
            );
        }

        if (! $slug) {
            throw new FraudCheckException('No default courier is configured for this store.', 'no_courier');
        }

        $courierName = app(CourierManager::class)->adapter($slug)->displayName();

        $checkerClass = self::CHECKERS[$slug] ?? null;
        if (! $checkerClass) {
            throw new FraudCheckException("Fraud checking is not supported by {$courierName}.", 'unsupported');
        }

        $result = app($checkerClass)->check($store, $phone);

        return [
            'courier_slug'  => $slug,
            'courier'       => $courierName,
            'phone'         => $phone,
            'summary'       => $result['summary'],
            // Courier-provided risk wins; otherwise compute from the counts.
            'risk'          => $result['risk'] ?? self::riskFromSummary($result['summary']),
            'raw_reference' => $result['raw_reference'] ?? null,
        ];
    }

    /**
     * Accepts 01XXXXXXXXX, +88 01XXXXXXXXX, 88 01XXXXXXXXX (with any spaces,
     * dashes or parentheses) and returns canonical 01XXXXXXXXX, or null.
     */
    public static function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 2); // 880 1XXXXXXXXX → 01XXXXXXXXX
        }
        return preg_match('/^01[3-9]\d{8}$/', $digits) ? $digits : null;
    }

    /** 017XXXXXXXX → 017****1234 (for logs / audit — never store the raw number where masking is expected). */
    public static function maskPhone(string $phone): string
    {
        return strlen($phone) >= 7
            ? substr($phone, 0, 3).'****'.substr($phone, -4)
            : '****';
    }

    /**
     * Balanced risk rules when the courier gives raw counts only:
     *  - no history                     → unknown (not suspicious by itself)
     *  - tiny sample (≤2 parcels)       → never "high"; one return ≠ fraud
     *  - success rate ≥ 80%             → low
     *  - success rate ≥ 50%             → medium
     *  - otherwise                      → high
     * Pending parcels are excluded from the rate (not yet an outcome).
     */
    public static function riskFromSummary(array $s): array
    {
        $total     = (int) ($s['total'] ?? 0);
        $delivered = (int) ($s['delivered'] ?? 0);
        $failed    = (int) ($s['returned'] ?? 0) + (int) ($s['cancelled'] ?? 0);
        $decided   = $delivered + $failed;

        if ($total === 0) {
            return ['level' => 'unknown', 'label' => 'Unable to Determine',
                    'message' => 'No delivery history was found for this phone number.'];
        }
        if ($decided === 0) {
            return ['level' => 'unknown', 'label' => 'Unable to Determine',
                    'message' => 'All parcels for this number are still pending — no completed deliveries to judge by.'];
        }

        $rate = $delivered / $decided * 100;

        if ($decided <= 2) {
            return $failed === 0
                ? ['level' => 'low', 'label' => 'Low Risk',
                   'message' => 'Small history, all delivered successfully.']
                : ['level' => 'medium', 'label' => 'Medium Risk',
                   'message' => 'Very small history with a return/cancellation — not enough data to call it fraud.'];
        }
        if ($rate >= 80) {
            return ['level' => 'low', 'label' => 'Low Risk',
                    'message' => 'Customer has a good delivery history.'];
        }
        if ($rate >= 50) {
            return ['level' => 'medium', 'label' => 'Medium Risk',
                    'message' => 'Mixed delivery history — confirm the order by phone before dispatch.'];
        }
        return ['level' => 'high', 'label' => 'High Risk',
                'message' => 'Most parcels to this number were returned or cancelled.'];
    }
}
