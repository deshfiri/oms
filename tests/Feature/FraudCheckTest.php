<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Services\Couriers\FraudCheck\FraudCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FraudCheckTest extends TestCase
{
    use RefreshDatabase;

    private function makeStore(array $overrides = []): Store
    {
        $store = new Store(array_merge([
            'name'           => 'Test Store',
            'business_name'  => 'Test Store',
            'dfid'           => 'DFTEST01',
            'base_url'       => 'https://shop.test',
            'api_key'        => 'k',
            'webhook_secret' => 'wh',
            'is_active'      => true,
        ], $overrides));
        $store->api_secret = 's';
        $store->save();
        return $store;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    /** Storefront couriers-settings + Steadfast fraud API, both faked. */
    private function fakeCourierApis(string $defaultCourier = 'steadfast', ?array $fraudBody = null, int $fraudStatus = 200): void
    {
        Http::fake([
            'shop.test/api/v1/settings/couriers' => Http::response([
                'ok' => true, 'data' => ['default_courier' => $defaultCourier],
            ]),
            'portal.packzy.com/api/v1/fraud_check/*' => Http::response(
                $fraudBody ?? ['status' => 200, 'total_delivered' => 16, 'total_cancelled' => 4],
                $fraudStatus,
            ),
        ]);
    }

    private function withSteadfastCreds(Store $store): Store
    {
        $store->courier_credentials = ['steadfast' => ['api_key' => 'sf-key', 'secret_key' => 'sf-secret']];
        $store->save();
        return $store;
    }

    // 1. Button is on the new-order page
    public function test_fraud_check_button_visible_on_create_page(): void
    {
        $this->makeStore();
        $this->actingAs($this->admin())
            ->get(route('orders-new.create'))
            ->assertOk()
            ->assertSee('Fraud Check')
            ->assertSee(route('orders-new.fraud-check'));
    }

    // 2. store and phone required
    public function test_store_and_phone_are_required(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['store_id', 'phone']);
    }

    // 3. invalid BD phone rejected
    public function test_invalid_bd_phone_rejected(): void
    {
        $store = $this->makeStore();
        foreach (['12345', '02712345678', '017123456789012', 'not-a-phone'] as $bad) {
            $this->actingAs($this->admin())
                ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => $bad])
                ->assertStatus(422);
        }
    }

    // 4. phone formats normalized before hitting the courier
    public function test_phone_formats_are_normalized(): void
    {
        $this->assertSame('01712345678', FraudCheckService::normalizePhone('01712345678'));
        $this->assertSame('01712345678', FraudCheckService::normalizePhone('+8801712345678'));
        $this->assertSame('01712345678', FraudCheckService::normalizePhone('8801712345678'));
        $this->assertSame('01712345678', FraudCheckService::normalizePhone('+88 017-1234 5678'));
        $this->assertNull(FraudCheckService::normalizePhone('0271234567'));

        $store = $this->withSteadfastCreds($this->makeStore());
        $this->fakeCourierApis();

        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '+8801712345678'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('phone', '01712345678');

        Http::assertSent(fn ($req) => str_contains($req->url(), 'fraud_check/01712345678'));
    }

    // 5. the store's default courier (from storefront settings) is used
    public function test_uses_stores_default_courier(): void
    {
        $store = $this->withSteadfastCreds($this->makeStore());
        $this->fakeCourierApis('steadfast');

        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertOk()
            ->assertJsonPath('courier', 'Steadfast');
    }

    // 6. frontend cannot override the courier
    public function test_frontend_cannot_override_courier(): void
    {
        $store = $this->withSteadfastCreds($this->makeStore());
        $this->fakeCourierApis('steadfast');

        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), [
                'store_id' => $store->id,
                'phone'    => '01712345678',
                'courier'  => 'redx',            // must be ignored
                'api_key'  => 'attacker-key',    // must be ignored
            ])
            ->assertOk()
            ->assertJsonPath('courier', 'Steadfast');

        Http::assertSent(fn ($req) => str_contains($req->url(), 'portal.packzy.com')
            && $req->header('Api-Key')[0] === 'sf-key');
    }

    // 7. no default courier configured → clear error
    public function test_missing_default_courier_returns_clear_error(): void
    {
        $store = $this->makeStore();
        Http::fake([
            'shop.test/api/v1/settings/couriers' => Http::response(['ok' => true, 'data' => []]),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No default courier is configured for this store.');
    }

    // 7b. no credentials anywhere (OMS + storefront) → clear error
    public function test_missing_credentials_returns_clear_error(): void
    {
        config(['couriers.steadfast.api_key' => null, 'couriers.steadfast.secret' => null]);
        $store = $this->makeStore(); // no per-store creds either → proxies to storefront
        Http::fake([
            'shop.test/api/v1/settings/couriers' => Http::response(['ok' => true, 'data' => ['default_courier' => 'steadfast']]),
            'shop.test/api/v1/couriers/fraud-check*' => Http::response([
                'ok' => true, 'data' => ['courier' => 'steadfast', 'supported' => true, 'configured' => false, 'raw' => null],
            ]),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "The selected store's courier integration is not configured properly.");
    }

    // 7c. keys live only on the STORE WEBSITE → proxied through it, still works
    public function test_storefront_held_credentials_work_via_proxy(): void
    {
        config(['couriers.steadfast.api_key' => null, 'couriers.steadfast.secret' => null]);
        $store = $this->makeStore(); // no OMS-side creds at all
        Http::fake([
            'shop.test/api/v1/settings/couriers' => Http::response(['ok' => true, 'data' => ['default_courier' => 'steadfast']]),
            'shop.test/api/v1/couriers/fraud-check*' => Http::response([
                'ok'   => true,
                'data' => ['courier' => 'steadfast', 'supported' => true, 'configured' => true,
                           'raw' => ['status' => 200, 'total_delivered' => 9, 'total_cancelled' => 1]],
            ]),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('courier', 'Steadfast')
            ->assertJsonPath('summary.total', 10)
            ->assertJsonPath('summary.delivered', 9)
            ->assertJsonPath('risk.level', 'low');

        // The proxy — not portal.packzy.com — must have been called.
        Http::assertSent(fn ($req) => str_contains($req->url(), 'shop.test/api/v1/couriers/fraud-check')
            && str_contains($req->url(), 'phone=01712345678'));
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'portal.packzy.com'));
    }

    // 7d. old storefront build without the proxy endpoint → clear guidance
    public function test_storefront_without_proxy_endpoint_reports_clearly(): void
    {
        config(['couriers.steadfast.api_key' => null, 'couriers.steadfast.secret' => null]);
        $store = $this->makeStore();
        Http::fake([
            'shop.test/api/v1/settings/couriers' => Http::response(['ok' => true, 'data' => ['default_courier' => 'steadfast']]),
            'shop.test/api/v1/couriers/fraud-check*' => Http::response(['ok' => false, 'error' => ['message' => 'Not found']], 404),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The store website does not support fraud checking yet — update the store website.');
    }

    // 8. unsupported courier → unsupported message
    public function test_unsupported_courier_returns_unsupported_message(): void
    {
        $store = $this->makeStore();
        $this->fakeCourierApis('redx');

        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Fraud checking is not supported by RedX.');
    }

    // 9. successful response normalized correctly (incl. risk rules)
    public function test_successful_response_is_normalized(): void
    {
        $store = $this->withSteadfastCreds($this->makeStore());
        $this->fakeCourierApis('steadfast', ['status' => 200, 'total_delivered' => 16, 'total_cancelled' => 3, 'total_returned' => 1]);

        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.total', 20)
            ->assertJsonPath('summary.delivered', 16)
            ->assertJsonPath('summary.returned', 1)
            ->assertJsonPath('summary.cancelled', 3)
            ->assertJsonPath('summary.success_rate', 80)
            ->assertJsonPath('summary.return_rate', 20)
            ->assertJsonPath('risk.level', 'low');
    }

    // 9b. balanced risk rules
    public function test_risk_rules_are_balanced(): void
    {
        // No history → unknown, never fraud.
        $this->assertSame('unknown', FraudCheckService::riskFromSummary(['total' => 0, 'delivered' => 0, 'returned' => 0, 'cancelled' => 0])['level']);
        // One return in a tiny history → medium at worst.
        $this->assertSame('medium', FraudCheckService::riskFromSummary(['total' => 2, 'delivered' => 1, 'returned' => 1, 'cancelled' => 0])['level']);
        // Good history → low.
        $this->assertSame('low', FraudCheckService::riskFromSummary(['total' => 20, 'delivered' => 18, 'returned' => 2, 'cancelled' => 0])['level']);
        // Mixed → medium.
        $this->assertSame('medium', FraudCheckService::riskFromSummary(['total' => 10, 'delivered' => 6, 'returned' => 4, 'cancelled' => 0])['level']);
        // Mostly failed → high.
        $this->assertSame('high', FraudCheckService::riskFromSummary(['total' => 10, 'delivered' => 3, 'returned' => 5, 'cancelled' => 2])['level']);
        // Pending-only history → unknown.
        $this->assertSame('unknown', FraudCheckService::riskFromSummary(['total' => 3, 'delivered' => 0, 'returned' => 0, 'cancelled' => 0, 'pending' => 3])['level']);
    }

    // 10. courier API failure → graceful, non-breaking answer
    public function test_courier_api_failure_is_graceful(): void
    {
        $store = $this->withSteadfastCreds($this->makeStore());
        $this->fakeCourierApis('steadfast', ['error' => 'boom'], 500);

        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The courier service is currently unavailable. Please try again.');
    }

    // 10b. endpoint missing on the account (404) → unsupported message
    public function test_courier_404_reports_unsupported(): void
    {
        $store = $this->withSteadfastCreds($this->makeStore());
        $this->fakeCourierApis('steadfast', ['message' => 'not found'], 404);

        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Fraud checking is not supported by this courier.');
    }

    // 11. user not assigned to the store cannot check it
    public function test_unassigned_user_cannot_check_other_store(): void
    {
        $store = $this->makeStore();
        $smm   = User::factory()->create(['role' => User::ROLE_SOCIAL_MEDIA_MANAGER, 'is_active' => true]);

        $this->actingAs($smm)
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertStatus(403);
    }

    // 11b. assigned SMM can check their store
    public function test_assigned_smm_can_check_their_store(): void
    {
        $store = $this->withSteadfastCreds($this->makeStore());
        $smm   = User::factory()->create(['role' => User::ROLE_SOCIAL_MEDIA_MANAGER, 'is_active' => true]);
        $smm->stores()->attach($store->id);
        $this->fakeCourierApis();

        $this->actingAs($smm)
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    // 12. fraud check never creates an order (or anything else)
    public function test_no_order_created_during_fraud_check(): void
    {
        $store = $this->withSteadfastCreds($this->makeStore());
        $this->fakeCourierApis();

        $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertOk();

        $this->assertDatabaseCount('orders_mirror', 0);
        $this->assertDatabaseCount('products_mirror', 0);
    }

    // 14. credentials never leak into the response; audit row is masked
    public function test_secrets_never_in_response_and_audit_is_masked(): void
    {
        $store = $this->withSteadfastCreds($this->makeStore());
        $this->fakeCourierApis();

        $resp = $this->actingAs($this->admin())
            ->postJson(route('orders-new.fraud-check'), ['store_id' => $store->id, 'phone' => '01712345678'])
            ->assertOk();

        $body = $resp->getContent();
        $this->assertStringNotContainsString('sf-key', $body);
        $this->assertStringNotContainsString('sf-secret', $body);

        $log = \App\Models\AuditLog::where('action', 'courier_fraud_check')->latest('id')->first();
        $this->assertNotNull($log, 'fraud check must write an audit log row');
        $this->assertSame('017****5678', $log->after_json['masked_phone'] ?? null);
        $this->assertStringNotContainsString('01712345678', json_encode($log->after_json));
        $this->assertSame('success', $log->after_json['status'] ?? null);
        $this->assertSame('low', $log->after_json['risk_level'] ?? null);
    }
}
