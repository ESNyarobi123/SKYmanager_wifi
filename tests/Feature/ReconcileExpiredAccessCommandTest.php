<?php

use App\Models\BillingPlan;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\WifiUser;
use App\Services\MikrotikApiService;

test('reconcile expired access marks expired subscriptions', function () {
    $mikrotik = Mockery::mock(MikrotikApiService::class);
    $mikrotik->shouldReceive('connect')->andReturnSelf();
    $mikrotik->shouldReceive('removeHotspotUser')->andReturn(true);
    $mikrotik->shouldReceive('disconnect')->andReturnNull();

    app()->instance(MikrotikApiService::class, $mikrotik);

    $plan = BillingPlan::factory()->create();
    $router = Router::factory()->create();
    $user = WifiUser::factory()->create(['is_active' => true]);

    $sub = Subscription::factory()->create([
        'wifi_user_id' => $user->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
        'status' => 'active',
        'expires_at' => now()->subMinutes(5),
    ]);

    $this->artisan('app:reconcile-expired-access')
        ->assertSuccessful();

    expect($sub->fresh()->status)->toBe('expired')
        ->and($user->fresh()->is_active)->toBeFalse();
});

test('reconcile expired access skips non-expired subscriptions', function () {
    $mikrotik = Mockery::mock(MikrotikApiService::class);
    $mikrotik->shouldNotReceive('connect');

    app()->instance(MikrotikApiService::class, $mikrotik);

    $plan = BillingPlan::factory()->create();
    $router = Router::factory()->create();
    $user = WifiUser::factory()->create(['is_active' => true]);

    $sub = Subscription::factory()->active()->create([
        'wifi_user_id' => $user->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
    ]);

    $this->artisan('app:reconcile-expired-access')
        ->assertSuccessful();

    expect($sub->fresh()->status)->toBe('active');
});

test('reconcile expired access outputs message when none found', function () {
    $mikrotik = Mockery::mock(MikrotikApiService::class);
    app()->instance(MikrotikApiService::class, $mikrotik);

    $this->artisan('app:reconcile-expired-access')
        ->expectsOutputToContain('No expired subscriptions found.')
        ->assertSuccessful();
});

test('reconcile expired access keeps wifi user active when another subscription is active', function () {
    $plan = BillingPlan::factory()->create();
    $router = Router::factory()->create();
    $user = WifiUser::factory()->create(['is_active' => true]);

    Subscription::factory()->create([
        'wifi_user_id' => $user->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
        'status' => 'active',
        'expires_at' => now()->subMinutes(5),
    ]);

    Subscription::factory()->active()->create([
        'wifi_user_id' => $user->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
    ]);

    $mikrotik = Mockery::mock(MikrotikApiService::class);
    $mikrotik->shouldReceive('connect')->once()->andReturnSelf();
    $mikrotik->shouldReceive('removeHotspotUser')->once()->andReturn(true);
    $mikrotik->shouldReceive('disconnect')->once();

    app()->instance(MikrotikApiService::class, $mikrotik);

    $this->artisan('app:reconcile-expired-access')->assertSuccessful();

    expect($user->fresh()->is_active)->toBeTrue();
});

test('reconcile expired access continues when mikrotik connection fails', function () {
    $plan = BillingPlan::factory()->create();
    $router = Router::factory()->create();
    $user = WifiUser::factory()->create(['is_active' => true]);

    $subscription = Subscription::factory()->create([
        'wifi_user_id' => $user->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
        'status' => 'active',
        'expires_at' => now()->subMinutes(5),
    ]);

    $mikrotik = Mockery::mock(MikrotikApiService::class);
    $mikrotik->shouldReceive('connect')->once()->andThrow(new Exception('Connection refused'));
    $mikrotik->shouldNotReceive('disconnect');

    app()->instance(MikrotikApiService::class, $mikrotik);

    $this->artisan('app:reconcile-expired-access')->assertSuccessful();

    expect($subscription->fresh()->status)->toBe('expired');
});
