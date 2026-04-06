<?php

use App\Livewire\Customer\ClientSessions;
use App\Models\BillingPlan;
use App\Models\Customer;
use App\Models\CustomerBillingPlan;
use App\Models\HotspotPayment;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\WifiUser;
use App\Services\CustomerClientSessionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('customer client sessions page requires authentication', function () {
    $this->get(route('customer.client-sessions'))
        ->assertRedirect();
});

test('customer sees only sessions for own routers', function () {
    $alice = Customer::factory()->create();
    $bob = Customer::factory()->create();

    $aliceRouter = Router::factory()->for($alice, 'user')->create();
    $bobRouter = Router::factory()->for($bob, 'user')->create();

    $plan = BillingPlan::factory()->create();
    $wifi = WifiUser::factory()->create();

    Subscription::factory()->active()->create([
        'wifi_user_id' => $wifi->id,
        'plan_id' => $plan->id,
        'router_id' => $aliceRouter->id,
    ]);

    $otherWifi = WifiUser::factory()->create();
    Subscription::factory()->active()->create([
        'wifi_user_id' => $otherWifi->id,
        'plan_id' => $plan->id,
        'router_id' => $bobRouter->id,
    ]);

    $service = app(CustomerClientSessionService::class);
    $aliceRows = $service->sessionsForCustomer($alice, ['tab' => 'all']);
    $bobRows = $service->sessionsForCustomer($bob, ['tab' => 'all']);

    expect($aliceRows)->toHaveCount(1);
    expect($bobRows)->toHaveCount(1);
    expect($aliceRows->first()->routerId)->toBe($aliceRouter->id);
    expect($bobRows->first()->routerId)->toBe($bobRouter->id);
});

test('active tab excludes expired subscription rows', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $plan = BillingPlan::factory()->create();
    $wifi = WifiUser::factory()->create();

    Subscription::factory()->expired()->create([
        'wifi_user_id' => $wifi->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
    ]);

    $service = app(CustomerClientSessionService::class);
    $active = $service->sessionsForCustomer($customer, ['tab' => 'active']);
    $history = $service->sessionsForCustomer($customer, ['tab' => 'history']);

    expect($active)->toHaveCount(0);
    expect($history)->toHaveCount(1);
});

test('hotspot payment pending appears in active tab and valid access filter excludes it', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $cbp = CustomerBillingPlan::factory()->create(['customer_id' => $customer->id]);

    HotspotPayment::query()->create([
        'router_id' => $router->id,
        'plan_id' => $cbp->id,
        'client_mac' => 'AA:BB:CC:DD:EE:02',
        'client_ip' => '10.0.0.6',
        'phone' => '255700000002',
        'amount' => 500,
        'reference' => 'HP-'.uniqid('', true),
        'status' => 'success',
    ]);

    $service = app(CustomerClientSessionService::class);
    $active = $service->sessionsForCustomer($customer, ['tab' => 'active']);
    expect($active)->toHaveCount(1);
    expect($active->first()->isPendingAccess)->toBeTrue();

    $validOnly = $service->sessionsForCustomer($customer, ['tab' => 'active', 'access' => 'valid']);
    expect($validOnly)->toHaveCount(0);

    $pendingOnly = $service->sessionsForCustomer($customer, ['tab' => 'active', 'access' => 'pending']);
    expect($pendingOnly)->toHaveCount(1);
});

test('router filter scopes rows', function () {
    $customer = Customer::factory()->create();
    $r1 = Router::factory()->for($customer, 'user')->create();
    $r2 = Router::factory()->for($customer, 'user')->create();
    $plan = BillingPlan::factory()->create();

    foreach ([$r1, $r2] as $r) {
        Subscription::factory()->active()->create([
            'wifi_user_id' => WifiUser::factory()->create()->id,
            'plan_id' => $plan->id,
            'router_id' => $r->id,
        ]);
    }

    $service = app(CustomerClientSessionService::class);
    $one = $service->sessionsForCustomer($customer, ['tab' => 'all', 'router_id' => $r1->id]);
    expect($one)->toHaveCount(1);
    expect($one->first()->routerId)->toBe($r1->id);
});

test('remaining label describes future expiry', function () {
    $future = Carbon::now()->addHours(2)->addMinutes(5);
    $label = CustomerClientSessionService::remainingLabel($future, true, false);
    expect($label)->toContain('left');
});

test('csv export returns streamed response for customer', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $plan = BillingPlan::factory()->create();
    Subscription::factory()->active()->create([
        'wifi_user_id' => WifiUser::factory()->create()->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
    ]);

    $this->actingAs($customer)
        ->get(route('customer.client-sessions.export', ['tab' => 'all']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('livewire client sessions lists subscription source label', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $plan = BillingPlan::factory()->create(['name' => 'Test Plan X']);
    Subscription::factory()->active()->create([
        'wifi_user_id' => WifiUser::factory()->create(['phone_number' => '255799999999'])->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
    ]);

    Livewire::actingAs($customer)
        ->test(ClientSessions::class)
        ->assertSee('Test Plan X')
        ->assertSee('255799999999');
});
