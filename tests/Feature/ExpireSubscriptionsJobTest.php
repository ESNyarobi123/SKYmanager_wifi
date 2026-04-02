<?php

use App\Jobs\ExpireSubscriptions;
use App\Models\BillingPlan;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\WifiUser;
use App\Services\MikrotikApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('job marks expired active subscriptions as expired', function () {
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
    $mikrotik->shouldReceive('connect')->once()->andReturnSelf();
    $mikrotik->shouldReceive('removeHotspotUser')->once()->andReturn(true);
    $mikrotik->shouldReceive('disconnect')->once();

    (new ExpireSubscriptions)->handle($mikrotik);

    expect($subscription->fresh()->status)->toBe('expired');
});

test('job deactivates wifi user when no active subscriptions remain', function () {
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

    $mikrotik = Mockery::mock(MikrotikApiService::class);
    $mikrotik->shouldReceive('connect')->once()->andReturnSelf();
    $mikrotik->shouldReceive('removeHotspotUser')->once()->andReturn(true);
    $mikrotik->shouldReceive('disconnect')->once();

    (new ExpireSubscriptions)->handle($mikrotik);

    expect($user->fresh()->is_active)->toBeFalse();
});

test('job does not touch subscriptions that are not yet expired', function () {
    $plan = BillingPlan::factory()->create();
    $router = Router::factory()->create();
    $user = WifiUser::factory()->create(['is_active' => true]);

    $subscription = Subscription::factory()->create([
        'wifi_user_id' => $user->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
        'status' => 'active',
        'expires_at' => now()->addHour(),
    ]);

    $mikrotik = Mockery::mock(MikrotikApiService::class);
    $mikrotik->shouldNotReceive('connect');

    (new ExpireSubscriptions)->handle($mikrotik);

    expect($subscription->fresh()->status)->toBe('active');
});

test('job continues if mikrotik connection fails', function () {
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

    (new ExpireSubscriptions)->handle($mikrotik);

    expect($subscription->fresh()->status)->toBe('expired');
});
