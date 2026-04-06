<?php

use App\Jobs\AuthorizeHotspotPaymentJob;
use App\Livewire\Admin\HotspotPaymentSupport;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerBillingPlan;
use App\Models\HotspotPayment;
use App\Models\Router;
use App\Models\User;
use App\Services\HotspotPaymentAuthorizationContextRecorder;
use App\Services\HotspotPaymentAuthorizationService;
use App\Services\MikrotikApiService;
use App\Services\PaymentIncidentSummaryService;
use App\Support\HotspotPaymentSupportHints;
use App\Support\HotspotPaymentTimeline;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Mockery\MockInterface;

afterEach(function () {
    Carbon::setTestNow();
});

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function bindLenientMikrotikMock(): MockInterface
{
    $m = Mockery::mock(MikrotikApiService::class)->shouldIgnoreMissing();
    app()->instance(MikrotikApiService::class, $m);
    app()->forgetInstance(HotspotPaymentAuthorizationService::class);

    return $m;
}

function createHotspotPaymentForAuthorize(): HotspotPayment
{
    $customer = Customer::factory()->create();
    $plan = CustomerBillingPlan::factory()->create(['customer_id' => $customer->id]);
    $router = Router::factory()->create(['user_id' => $customer->id]);

    return HotspotPayment::create([
        'router_id' => $router->id,
        'plan_id' => $plan->id,
        'client_mac' => 'AA:BB:CC:DD:EE:FF',
        'client_ip' => '192.168.88.10',
        'phone' => '255712345678',
        'amount' => $plan->price,
        'reference' => 'HP-OBS-'.uniqid(),
        'status' => 'success',
        'transaction_id' => 'txn-'.uniqid(),
        'provider_confirmed_at' => now(),
        'authorize_attempts' => 1,
    ]);
}

test('authorize failure persists health snapshot and activity log', function () {
    $payment = createHotspotPaymentForAuthorize();

    $mikrotik = bindLenientMikrotikMock();
    $mikrotik->shouldReceive('connectZtp')->once()->andReturnSelf();
    $mikrotik->shouldReceive('authorizeHotspotMac')->once()->andThrow(new RuntimeException('invalid user name or password'));
    $mikrotik->shouldReceive('disconnect')->zeroOrMoreTimes();

    $ok = app(HotspotPaymentAuthorizationService::class)->authorizePayment($payment->fresh());

    expect($ok)->toBeFalse();

    $payment->refresh();

    expect($payment->last_authorize_error)->toContain('invalid user')
        ->and($payment->first_authorize_failure_at)->not->toBeNull()
        ->and($payment->last_authorize_failed_at)->not->toBeNull()
        ->and($payment->last_authorize_error_code)->toBe('api_auth')
        ->and($payment->last_authorize_health_snapshot)->toBeArray()
        ->and($payment->provider_confirmed_at_failure)->toBeTrue();

    expect(ActivityLog::query()
        ->where('subject_type', HotspotPayment::class)
        ->where('subject_id', $payment->id)
        ->where('description', 'Hotspot payment authorize failed')
        ->exists())->toBeTrue();
});

test('second authorize failure keeps first failure timestamp and updates last', function () {
    Carbon::setTestNow('2026-04-06 10:00:00');
    $payment = createHotspotPaymentForAuthorize();

    $mikrotik = bindLenientMikrotikMock();
    $mikrotik->shouldReceive('connectZtp')->twice()->andReturnSelf();
    $mikrotik->shouldReceive('authorizeHotspotMac')->twice()->andThrow(new RuntimeException('timed out'));
    $mikrotik->shouldReceive('disconnect')->zeroOrMoreTimes();

    app(HotspotPaymentAuthorizationService::class)->authorizePayment($payment->fresh());
    $first = $payment->fresh()->first_authorize_failure_at;

    Carbon::setTestNow('2026-04-06 11:00:00');
    app(HotspotPaymentAuthorizationService::class)->authorizePayment($payment->fresh());

    $payment->refresh();

    expect($payment->first_authorize_failure_at?->equalTo($first))->toBeTrue()
        ->and($payment->last_authorize_failed_at?->gt($first))->toBeTrue();
});

test('authorize success after failure records recovery analytics', function () {
    $payment = createHotspotPaymentForAuthorize();
    $payment->update([
        'first_authorize_failure_at' => now()->subMinutes(5),
        'authorize_attempts' => 4,
    ]);

    $mikrotik = bindLenientMikrotikMock();
    $mikrotik->shouldReceive('connectZtp')->once()->andReturnSelf();
    $mikrotik->shouldReceive('authorizeHotspotMac')->once();
    $mikrotik->shouldReceive('disconnect')->zeroOrMoreTimes();

    $ok = app(HotspotPaymentAuthorizationService::class)->authorizePayment($payment->fresh());

    expect($ok)->toBeTrue();

    $payment->refresh();

    expect($payment->status)->toBe('authorized')
        ->and($payment->recovered_after_failure_at)->not->toBeNull()
        ->and($payment->failed_authorize_attempts_before_success)->toBe(3);

    expect(ActivityLog::query()
        ->where('subject_type', HotspotPayment::class)
        ->where('subject_id', $payment->id)
        ->where('description', 'Hotspot payment authorized after prior failure')
        ->exists())->toBeTrue();
});

test('authorize job records attempts exhausted when at max attempts', function () {
    config(['skymanager.hotspot_authorize_max_attempts' => 3]);

    $payment = createHotspotPaymentForAuthorize();
    $payment->update(['authorize_attempts' => 3]);

    $mikrotik = bindLenientMikrotikMock();
    $mikrotik->shouldNotReceive('connectZtp');

    $job = new AuthorizeHotspotPaymentJob($payment->id);
    $job->handle(
        app(HotspotPaymentAuthorizationService::class),
        app(HotspotPaymentAuthorizationContextRecorder::class),
    );

    $payment->refresh();

    expect($payment->authorize_retry_exhausted_at)->not->toBeNull();

    expect(ActivityLog::query()
        ->where('subject_type', HotspotPayment::class)
        ->where('subject_id', $payment->id)
        ->where('description', 'Hotspot payment authorize retries exhausted')
        ->exists())->toBeTrue();
});

test('queue retries exhausted recorder sets timestamp once', function () {
    $payment = createHotspotPaymentForAuthorize();
    $payment->update(['last_authorize_error' => 'queue died']);

    $recorder = app(HotspotPaymentAuthorizationContextRecorder::class);
    $recorder->recordQueueRetriesExhausted($payment->fresh(), 'final');
    $t1 = $payment->fresh()->authorize_retry_exhausted_at;

    $recorder->recordQueueRetriesExhausted($payment->fresh(), 'final again');
    $t2 = $payment->fresh()->authorize_retry_exhausted_at;

    expect($t1)->not->toBeNull()
        ->and($t2?->equalTo($t1))->toBeTrue();

    expect(ActivityLog::query()
        ->where('subject_type', HotspotPayment::class)
        ->where('subject_id', $payment->id)
        ->where('description', 'Hotspot payment authorize job exhausted (queue)')
        ->count())->toBe(1);
});

test('payment incident summary counts stuck and exhausted hotspot payments', function () {
    config(['skymanager.hotspot_authorize_max_attempts' => 5]);

    $p1 = createHotspotPaymentForAuthorize();
    $p1->update([
        'reference' => 'HP-SUM-STUCK-1',
        'last_authorize_error' => 'x',
        'authorize_attempts' => 1,
    ]);

    $p2 = createHotspotPaymentForAuthorize();
    $p2->update([
        'reference' => 'HP-SUM-EX-1',
        'authorize_attempts' => 5,
        'authorize_retry_exhausted_at' => now(),
    ]);

    $summary = app(PaymentIncidentSummaryService::class)->summarize();

    expect($summary['hotspot_stuck_authorizing'])->toBeGreaterThanOrEqual(1)
        ->and($summary['hotspot_retry_exhausted'])->toBeGreaterThanOrEqual(1);
});

test('support hints compare failure-time router offline with live online', function () {
    $payment = createHotspotPaymentForAuthorize();
    $payment->update([
        'last_authorize_failed_at' => now(),
        'provider_confirmed_at_failure' => true,
        'last_failure_router_online' => false,
        'last_authorize_error_code' => 'network',
    ]);

    $router = $payment->router;
    $router->update(['is_online' => true]);

    $live = [
        'overall' => 'healthy',
        'tunnel' => ['level' => 'healthy'],
        'api' => ['level' => 'healthy'],
        'portal' => ['level' => 'healthy'],
    ];

    $hints = HotspotPaymentSupportHints::forPayment($payment->fresh(), $router->fresh(), $live);
    $flat = implode(' ', $hints);

    expect($flat)->toContain('offline');
});

test('hotspot payment timeline includes provider confirm and failures', function () {
    $payment = createHotspotPaymentForAuthorize();
    $payment->update([
        'provider_confirmed_at' => now()->subHour(),
        'authorization_job_dispatched_at' => now()->subMinutes(30),
        'first_authorize_failure_at' => now()->subMinutes(20),
        'last_authorize_failed_at' => now()->subMinutes(10),
        'last_authorize_error' => 'boom',
    ]);

    $rows = HotspotPaymentTimeline::build($payment->fresh());
    $labels = collect($rows)->pluck('label')->map(fn ($l) => (string) $l)->all();

    expect($labels)->toContain('Payment initiated')
        ->and($labels)->toContain('Provider confirmed')
        ->and($labels)->toContain('Authorize job queued');
});

test('hotspot payment support detail renders when expanded', function () {
    $admin = User::factory()->admin()->create();
    $payment = createHotspotPaymentForAuthorize();
    $payment->update(['last_authorize_failed_at' => now(), 'last_authorize_error' => 'test']);

    Livewire::actingAs($admin)
        ->test(HotspotPaymentSupport::class)
        ->call('toggleExpand', $payment->id)
        ->assertSee('Support timeline', false)
        ->assertSee('At last failure (persisted)', false);
});
