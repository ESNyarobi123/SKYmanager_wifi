<?php

use App\Models\BillingPlan;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\WifiUser;
use App\Services\MikrotikApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('expire sessions command marks expired subscriptions', function () {
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

    $this->artisan('app:expire-sessions')
        ->assertSuccessful();

    expect($sub->fresh()->status)->toBe('expired')
        ->and($user->fresh()->is_active)->toBeFalse();
});

test('expire sessions command skips non-expired subscriptions', function () {
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

    $this->artisan('app:expire-sessions')
        ->assertSuccessful();

    expect($sub->fresh()->status)->toBe('active');
});

test('expire sessions outputs no expired sessions message when none found', function () {
    $mikrotik = Mockery::mock(MikrotikApiService::class);
    app()->instance(MikrotikApiService::class, $mikrotik);

    $this->artisan('app:expire-sessions')
        ->expectsOutputToContain('No expired sessions found.')
        ->assertSuccessful();
});
