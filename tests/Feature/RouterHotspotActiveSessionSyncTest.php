<?php

use App\Livewire\Customer\ClientSessions;
use App\Models\BillingPlan;
use App\Models\Customer;
use App\Models\CustomerBillingPlan;
use App\Models\HotspotPayment;
use App\Models\Router;
use App\Models\RouterHotspotActiveSession;
use App\Models\Subscription;
use App\Models\WifiUser;
use App\Services\MikrotikApiService;
use App\Services\RouterActiveSessionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('sync stores hotspot active rows from RouterOS API response', function () {
    $this->mock(MikrotikApiService::class, function ($mock) {
        $mock->shouldReceive('connect')->once()->andReturnSelf();
        $mock->shouldReceive('getActiveHotspotSessions')->once()->andReturn([
            [
                '.id' => '*1',
                'mac-address' => 'AA:BB:CC:DD:EE:01',
                'bytes-in' => '1048576',
                'bytes-out' => '2097152',
                'user' => 'guest1',
                'address' => '10.9.9.9',
            ],
        ]);
        $mock->shouldReceive('disconnect')->once();
    });

    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create([
        'ip_address' => '192.168.88.1',
    ]);

    $result = app(RouterActiveSessionSyncService::class)->sync($router);

    expect($result['ok'])->toBeTrue();
    expect($result['sessions'])->toBe(1);

    $router->refresh();
    expect($router->hotspot_sessions_synced_at)->not->toBeNull();
    expect($router->hotspot_sessions_sync_error)->toBeNull();

    expect(RouterHotspotActiveSession::query()->where('router_id', $router->id)->count())->toBe(1);
    $row = RouterHotspotActiveSession::query()->where('router_id', $router->id)->first();
    expect($row->mac_address)->toBe('AA:BB:CC:DD:EE:01');
    expect($row->bytes_in)->toBe(1048576);
    expect($row->bytes_out)->toBe(2097152);
});

test('sync updates hotspot payment bytes when exactly one authorized payment matches mac', function () {
    $this->mock(MikrotikApiService::class, function ($mock) {
        $mock->shouldReceive('connect')->once()->andReturnSelf();
        $mock->shouldReceive('getActiveHotspotSessions')->once()->andReturn([
            [
                '.id' => '*2',
                'mac-address' => 'AA:BB:CC:DD:EE:99',
                'bytes-in' => '500',
                'bytes-out' => '700',
            ],
        ]);
        $mock->shouldReceive('disconnect')->once();
    });

    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create(['ip_address' => '10.0.0.2']);
    $plan = CustomerBillingPlan::factory()->create(['customer_id' => $customer->id]);

    HotspotPayment::query()->create([
        'router_id' => $router->id,
        'plan_id' => $plan->id,
        'client_mac' => 'AA:BB:CC:DD:EE:99',
        'client_ip' => '10.0.0.50',
        'phone' => '255700000099',
        'amount' => 100,
        'reference' => 'REF-'.uniqid('', true),
        'status' => 'authorized',
        'authorized_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    app(RouterActiveSessionSyncService::class)->sync($router);

    $payment = HotspotPayment::query()->where('router_id', $router->id)->first();
    expect($payment)->not->toBeNull();
    expect($payment->router_bytes_in)->toBe(500);
    expect($payment->router_bytes_out)->toBe(700);
    expect($payment->router_usage_synced_at)->not->toBeNull();
});

test('customer session row shows online fresh when snapshot matches mac and sync is recent', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $router->forceFill([
        'hotspot_sessions_synced_at' => now(),
        'hotspot_sessions_sync_error' => null,
    ])->save();

    $wifi = WifiUser::factory()->create(['mac_address' => '11:22:33:44:55:66']);
    $plan = BillingPlan::factory()->create();
    Subscription::factory()->active()->create([
        'wifi_user_id' => $wifi->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
    ]);

    RouterHotspotActiveSession::query()->create([
        'router_id' => $router->id,
        'mikrotik_internal_id' => '*9',
        'mac_address' => '11:22:33:44:55:66',
        'ip_address' => '10.1.2.3',
        'user_name' => 'subuser',
        'bytes_in' => 1024,
        'bytes_out' => 2048,
        'uptime_seconds' => 120,
        'uptime_raw' => null,
        'synced_at' => now(),
    ]);

    Livewire::actingAs($customer)
        ->test(ClientSessions::class)
        ->assertSee(__('Online now (router)'), false);
});

test('stale sync shows stale router badge for matching mac', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $router->forceFill([
        'hotspot_sessions_synced_at' => now()->subHours(2),
    ])->save();

    $wifi = WifiUser::factory()->create(['mac_address' => 'AA:AA:AA:AA:AA:AA']);
    $plan = BillingPlan::factory()->create();
    Subscription::factory()->active()->create([
        'wifi_user_id' => $wifi->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
    ]);

    RouterHotspotActiveSession::query()->create([
        'router_id' => $router->id,
        'mikrotik_internal_id' => '*8',
        'mac_address' => 'AA:AA:AA:AA:AA:AA',
        'bytes_in' => 100,
        'bytes_out' => 200,
        'synced_at' => now()->subHours(2),
    ]);

    Livewire::actingAs($customer)
        ->test(ClientSessions::class)
        ->assertSee(__('Listed on router (old sync)'), false);
});

test('other customer does not see router snapshot from alien router', function () {
    $alice = Customer::factory()->create();
    $bob = Customer::factory()->create();

    $routerA = Router::factory()->for($alice, 'user')->create();
    $routerA->forceFill(['hotspot_sessions_synced_at' => now()])->save();

    RouterHotspotActiveSession::query()->create([
        'router_id' => $routerA->id,
        'mikrotik_internal_id' => '*1',
        'mac_address' => 'BB:BB:BB:BB:BB:BB',
        'bytes_in' => 1,
        'bytes_out' => 2,
        'synced_at' => now(),
    ]);

    $wifi = WifiUser::factory()->create(['mac_address' => 'BB:BB:BB:BB:BB:BB']);
    $plan = BillingPlan::factory()->create();
    Subscription::factory()->active()->create([
        'wifi_user_id' => $wifi->id,
        'plan_id' => $plan->id,
        'router_id' => $routerA->id,
    ]);

    Livewire::actingAs($bob)
        ->test(ClientSessions::class)
        ->assertDontSee(__('Online now (router)'));
});
