<?php

use App\Livewire\Admin\ReportingHub;
use App\Models\ActivityLog;
use App\Models\BillingPlan;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WifiUser;
use App\Services\OperationsReportService;
use App\Services\PaymentIncidentSummaryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('customer cannot access admin report export routes', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('admin.exports.download', ['type' => 'revenue', 'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()]))
        ->assertForbidden();
});

test('admin can download revenue csv', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.exports.download', ['type' => 'revenue', 'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect(ActivityLog::query()->where('description', 'Report CSV exported')->count())->toBe(1);
});

test('reseller revenue report excludes other accounts routers', function () {
    $plan = BillingPlan::factory()->create();

    $ownerA = User::factory()->reseller()->create();
    $ownerB = User::factory()->reseller()->create();

    $routerA = Router::factory()->create(['user_id' => $ownerA->id]);
    Router::factory()->create(['user_id' => $ownerB->id]);

    $wifi = WifiUser::factory()->create();
    $sub = Subscription::factory()->create([
        'wifi_user_id' => $wifi->id,
        'plan_id' => $plan->id,
        'router_id' => $routerA->id,
        'status' => 'active',
        'expires_at' => now()->addDay(),
    ]);

    Payment::factory()->successful()->create([
        'subscription_id' => $sub->id,
        'amount' => 9999,
        'created_at' => now(),
    ]);

    $svc = app(OperationsReportService::class);
    $from = now()->subDay();
    $to = now()->addDay();

    $rowsA = $svc->revenueReport($ownerA, $from, $to);
    $rowsB = $svc->revenueReport($ownerB, $from, $to);

    expect($rowsA)->toHaveCount(1)
        ->and($rowsB)->toHaveCount(0);
});

test('payment incident summary scopes to router owner', function () {
    $owner = User::factory()->customer()->create();
    $other = User::factory()->customer()->create();

    $r1 = Router::factory()->create(['user_id' => $owner->id, 'onboarding_status' => 'offline']);
    Router::factory()->create(['user_id' => $other->id, 'onboarding_status' => 'offline']);

    $global = app(PaymentIncidentSummaryService::class)->summarize();
    $scoped = app(PaymentIncidentSummaryService::class)->summarize($owner->id);

    expect($scoped['routers_offline_status'])->toBe(1)
        ->and($global['routers_offline_status'])->toBeGreaterThanOrEqual(2);
});

test('reporting hub livewire loads for user with reports.view', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(ReportingHub::class)
        ->assertOk();
});

test('customer can export own invoices csv', function () {
    $customer = User::factory()->customer()->create();
    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
        'issued_at' => now(),
        'status' => 'paid',
    ]);

    $response = $this->actingAs($customer)
        ->get(route('customer.invoices.export', ['from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString()]));

    $response->assertOk();

    expect($response->streamedContent())->toContain($invoice->invoice_number);
});
