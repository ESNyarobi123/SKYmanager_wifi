<?php

use App\Jobs\AuthorizeHotspotPaymentJob;
use App\Livewire\Admin\HotspotPaymentSupport;
use App\Livewire\Admin\RouterOperationsDashboard;
use App\Livewire\Admin\RouterOperationsDetail;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerBillingPlan;
use App\Models\HotspotPayment;
use App\Models\Router;
use App\Models\User;
use App\Support\AdminRouterSupportHints;
use App\Support\RouterOperationalReadiness;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guest is redirected from router operations', function () {
    $this->get(route('admin.router-operations'))
        ->assertRedirect(route('login'));
});

test('customer cannot access router operations dashboard', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('admin.router-operations'))
        ->assertForbidden();
});

test('admin can access router operations dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.router-operations'))
        ->assertOk();
});

test('admin can open router operations detail', function () {
    $admin = User::factory()->admin()->create();
    $router = Router::factory()->create(['user_id' => Customer::factory()->create()->id]);

    $this->actingAs($admin)
        ->get(route('admin.router-operations.show', $router))
        ->assertOk();
});

test('customer cannot access hotspot payment support', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('admin.hotspot-payment-support'))
        ->assertForbidden();
});

test('admin repair rotate credentials creates activity log', function () {
    $admin = User::factory()->admin()->create();
    $router = Router::factory()->create(['user_id' => Customer::factory()->create()->id]);

    Livewire::actingAs($admin)
        ->test(RouterOperationsDetail::class, ['router' => $router])
        ->call('actionRotateCredentials');

    expect(ActivityLog::query()
        ->where('subject_type', Router::class)
        ->where('subject_id', $router->id)
        ->where('description', 'ZTP API credentials rotated from admin')
        ->exists())->toBeTrue();
});

test('user with only view permission cannot run repair action', function () {
    $viewer = User::factory()->create();
    $viewer->syncRoles([]);
    $viewer->givePermissionTo(Permission::findByName('router-operations.view', 'web'));

    $router = Router::factory()->create(['user_id' => Customer::factory()->create()->id]);

    Livewire::actingAs($viewer)
        ->test(RouterOperationsDetail::class, ['router' => $router])
        ->call('actionRotateCredentials')
        ->assertForbidden();
});

test('hotspot payment support retry resets attempts and dispatches job', function () {
    Bus::fake([AuthorizeHotspotPaymentJob::class]);

    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create();
    $plan = CustomerBillingPlan::factory()->create(['customer_id' => $customer->id]);
    $router = Router::factory()->create(['user_id' => $customer->id]);

    $payment = HotspotPayment::create([
        'router_id' => $router->id,
        'plan_id' => $plan->id,
        'client_mac' => 'AA:BB:CC:DD:EE:FF',
        'client_ip' => '192.168.88.10',
        'phone' => '255712345678',
        'amount' => $plan->price,
        'reference' => 'HP-TEST-RETRY-001',
        'status' => 'success',
        'transaction_id' => 'txn-r',
        'authorize_attempts' => 7,
        'last_authorize_error' => 'previous failure',
    ]);

    Livewire::actingAs($admin)
        ->test(HotspotPaymentSupport::class)
        ->call('retryAuthorization', $payment->id);

    $payment->refresh();

    expect($payment->authorize_attempts)->toBe(0)
        ->and($payment->last_authorize_error)->toBeNull()
        ->and($payment->admin_authorize_retry_count)->toBe(1)
        ->and($payment->last_admin_authorize_retry_at)->not->toBeNull();

    Bus::assertDispatched(AuthorizeHotspotPaymentJob::class);

    expect(ActivityLog::query()
        ->where('subject_type', HotspotPayment::class)
        ->where('subject_id', $payment->id)
        ->where('description', 'Hotspot payment admin authorize retry')
        ->count())->toBe(1);
});

test('router operational readiness marks legacy bundle mode when not bundle', function () {
    $router = Router::factory()->create([
        'user_id' => Customer::factory()->create()->id,
        'bundle_deployment_mode' => null,
        'portal_bundle_hash' => null,
    ]);

    $snap = RouterOperationalReadiness::snapshot($router->fresh());

    expect($snap['bundle_mode'])->toBe('unknown');
});

test('admin support hints mention long claimed state', function () {
    $router = Router::factory()->create([
        'user_id' => Customer::factory()->create()->id,
        'onboarding_status' => 'claimed',
        'script_generated_at' => null,
    ]);

    $hints = AdminRouterSupportHints::forRouter($router->fresh());
    $flat = implode(' ', $hints);

    expect($flat)->toContain('script');
});

test('router operations dashboard livewire mounts for authorized admin', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(RouterOperationsDashboard::class)
        ->assertOk();
});
