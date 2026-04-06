<?php

use App\Jobs\AuthorizeHotspotPaymentJob;
use App\Models\CustomerBillingPlan;
use App\Models\CustomerVoucher;
use App\Models\HotspotPayment;
use App\Models\Router;
use App\Models\User;
use App\Services\HotspotPaymentAuthorizationContextRecorder;
use App\Services\HotspotPaymentAuthorizationService;
use App\Services\MikrotikApiService;
use Illuminate\Support\Facades\Bus;

test('ClickPesa callback rejects request when webhook secret is set and signature is missing', function () {
    config(['services.clickpesa.webhook_secret' => 'test-secret-key']);

    $this->postJson('/api/local-portal/payment/callback', [
        'orderReference' => 'HP-ABCDEFGHIJKL',
        'status' => 'SUCCESS',
    ])->assertStatus(401)
        ->assertJsonPath('code', 'invalid_signature');
});

test('ClickPesa callback confirms payment idempotently and dispatches authorize job once', function () {
    Bus::fake([AuthorizeHotspotPaymentJob::class]);

    $user = User::factory()->customer()->create();
    $plan = CustomerBillingPlan::factory()->create(['customer_id' => $user->id]);
    $router = Router::factory()->create(['user_id' => $user->id]);

    $payment = HotspotPayment::create([
        'router_id' => $router->id,
        'plan_id' => $plan->id,
        'client_mac' => 'AA:BB:CC:DD:EE:FF',
        'client_ip' => '192.168.88.10',
        'phone' => '255712345678',
        'amount' => $plan->price,
        'reference' => 'HP-ABCDEFGHIJKL',
        'status' => 'pending',
        'transaction_id' => 'txn-test-1',
    ]);

    $payload = [
        'orderReference' => $payment->reference,
        'status' => 'SUCCESS',
        'id' => 'ext-txn-99',
    ];

    $this->postJson('/api/local-portal/payment/callback', $payload)->assertOk();
    $this->postJson('/api/local-portal/payment/callback', $payload)->assertOk();

    $payment->refresh();

    expect($payment->status)->toBe('success')
        ->and($payment->provider_confirmed_at)->not->toBeNull();

    Bus::assertDispatchedTimes(AuthorizeHotspotPaymentJob::class, 1);
});

test('local portal payment initiate rejects when portal token mismatch', function () {
    $user = User::factory()->customer()->create();
    $plan = CustomerBillingPlan::factory()->create(['customer_id' => $user->id]);
    $router = Router::factory()->create([
        'user_id' => $user->id,
        'local_portal_token' => 'correct-secret-token',
    ]);

    $this->postJson('/api/local-portal/payment/initiate', [
        'router_id' => $router->id,
        'plan_id' => $plan->id,
        'phone' => '255712345678',
        'mac' => 'AA:BB:CC:DD:EE:FF',
        'ip' => '192.168.88.10',
    ], [
        'X-SKY-Portal-Token' => 'wrong',
    ])->assertStatus(403)
        ->assertJsonPath('code', 'portal_token_mismatch');
});

test('authorize hotspot payment job grants access when router API succeeds', function () {
    $mikrotik = Mockery::mock(MikrotikApiService::class);
    $mikrotik->shouldReceive('connectZtp')->once()->andReturnSelf();
    $mikrotik->shouldReceive('authorizeHotspotMac')->once();
    $mikrotik->shouldReceive('disconnect')->once();

    app()->instance(MikrotikApiService::class, $mikrotik);

    $user = User::factory()->customer()->create();
    $plan = CustomerBillingPlan::factory()->create(['customer_id' => $user->id]);
    $router = Router::factory()->create(['user_id' => $user->id]);

    $payment = HotspotPayment::create([
        'router_id' => $router->id,
        'plan_id' => $plan->id,
        'client_mac' => 'AA:BB:CC:DD:EE:FF',
        'client_ip' => '192.168.88.10',
        'phone' => '255712345678',
        'amount' => $plan->price,
        'reference' => 'HP-AAAAAAAAAAAA',
        'status' => 'success',
        'transaction_id' => 'txn-ok',
        'provider_confirmed_at' => now(),
    ]);

    (new AuthorizeHotspotPaymentJob($payment->id))->handle(
        app(HotspotPaymentAuthorizationService::class),
        app(HotspotPaymentAuthorizationContextRecorder::class),
    );

    expect($payment->fresh()->status)->toBe('authorized');
});

test('customer voucher redeem uses customer billing plan universe', function () {
    $mikrotik = Mockery::mock(MikrotikApiService::class);
    $mikrotik->shouldReceive('connectZtp')->once()->andReturnSelf();
    $mikrotik->shouldReceive('authorizeHotspotMac')->once();
    $mikrotik->shouldReceive('disconnect')->once();
    app()->instance(MikrotikApiService::class, $mikrotik);

    $voucher = CustomerVoucher::factory()->create();
    $router = Router::factory()->create(['user_id' => $voucher->customer_id]);

    $this->postJson('/api/local-portal/voucher/redeem', [
        'router_id' => $router->id,
        'code' => $voucher->code,
        'mac' => '11:22:33:44:55:66',
        'ip' => '192.168.88.20',
    ])->assertOk()
        ->assertJsonPath('status', 'authorized');

    expect($voucher->fresh()->status)->toBe('used');
});

test('customer voucher cannot be redeemed on another customers router', function () {
    $voucher = CustomerVoucher::factory()->create();
    $other = User::factory()->customer()->create();
    $router = Router::factory()->create(['user_id' => $other->id]);

    $this->postJson('/api/local-portal/voucher/redeem', [
        'router_id' => $router->id,
        'code' => $voucher->code,
        'mac' => '11:22:33:44:55:66',
        'ip' => '192.168.88.20',
    ])->assertStatus(422)
        ->assertJsonPath('code', 'voucher_invalid');
});
