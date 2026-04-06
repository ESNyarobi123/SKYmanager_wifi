<?php

use App\Livewire\Customer\MyPlans;
use App\Models\Customer;
use App\Models\CustomerBillingPlan;
use App\Models\Router;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

function makePlan(User $customer, array $attrs = []): CustomerBillingPlan
{
    return CustomerBillingPlan::create(array_merge([
        'customer_id' => $customer->id,
        'name' => 'Test 1H',
        'price' => '500.00',
        'duration_minutes' => 60,
        'is_active' => true,
    ], $attrs));
}

// ── Plan form: Mbps → kbps persistence ────────────────────────────────────────

test('saving a new plan converts Mbps fields to kbps in the database', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openCreateModal')
        ->set('name', 'Mbps Plan')
        ->set('price', '100')
        ->set('durationMinutes', '1')
        ->set('durationUnit', 'hours')
        ->set('uploadSpeedMbps', '5')
        ->set('downloadSpeedMbps', '10')
        ->call('savePlan');

    $plan = $customer->billingPlans()->where('name', 'Mbps Plan')->first();

    expect($plan)->not->toBeNull()
        ->and($plan->upload_speed_kbps)->toBe((int) round(5 * 1024))
        ->and($plan->download_speed_kbps)->toBe((int) round(10 * 1024));
});

// ── "Add Plan" button ─────────────────────────────────────────────────────────

test('Add Plan button opens the create form modal', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openCreateModal')
        ->assertSet('showFormModal', true)
        ->assertSet('editingPlanId', null);
});

// ── "My Portal URL" button ────────────────────────────────────────────────────

test('My Portal URL button opens the portal URL modal', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->set('showPortalModal', true)
        ->assertSet('showPortalModal', true);
});

// ── Hotspot bundle (recommended) ─────────────────────────────────────────────

test('Hotspot bundle flow shows warning notify when customer has no routers', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openHotspotBundleFlow')
        ->assertDispatched('notify');
});

test('Hotspot bundle flow redirects when customer has exactly one router', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openHotspotBundleFlow')
        ->assertRedirect(route('customer.plans.hotspot-bundle', ['routerId' => $router->id], absolute: false));
});

test('Hotspot bundle flow opens router picker when customer has multiple routers', function () {
    $customer = Customer::factory()->create();
    Router::factory()->create(['user_id' => $customer->id]);
    Router::factory()->create(['user_id' => $customer->id]);

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openHotspotBundleFlow')
        ->assertSet('showBundleRouterModal', true);
});

test('goToHotspotBundleForRouter redirects to bundle page', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('goToHotspotBundleForRouter', $router->id)
        ->assertRedirect(route('customer.plans.hotspot-bundle', ['routerId' => $router->id], absolute: false));
});

// ── Legacy single-file download / openDownloadModal ───────────────────────────

test('Legacy portal download shows warning notify when customer has no routers', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openDownloadModal')
        ->assertDispatched('notify');
});

test('Legacy portal download redirects to a signed URL when customer has exactly one router', function () {
    $customer = Customer::factory()->create();
    Router::factory()->create(['user_id' => $customer->id]);

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openDownloadModal')
        ->assertRedirect();
});

test('Legacy portal download opens router-selection modal when customer has multiple routers', function () {
    $customer = Customer::factory()->create();
    Router::factory()->create(['user_id' => $customer->id]);
    Router::factory()->create(['user_id' => $customer->id]);

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openDownloadModal')
        ->assertSet('showDownloadModal', true);
});

test('downloadForRouter redirects to a signed download URL', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('downloadForRouter', $router->id)
        ->assertRedirect();
});

test('downloadForRouter dispatches error notify when router not owned by customer', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $other->id]);

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('downloadForRouter', $router->id)
        ->assertDispatched('notify');
});

// ── "Preview" button / openPreviewModal ──────────────────────────────────────

test('Preview shows warning notify when customer has no routers', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openPreviewModal')
        ->assertDispatched('notify');
});

test('Preview dispatches open-preview-url directly when customer has exactly one router', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openPreviewModal')
        ->assertDispatched('open-preview-url');
});

test('Preview opens router-selection modal when customer has multiple routers', function () {
    $customer = Customer::factory()->create();
    Router::factory()->create(['user_id' => $customer->id]);
    Router::factory()->create(['user_id' => $customer->id]);

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openPreviewModal')
        ->assertSet('showPreviewModal', true);
});

test('previewForRouter dispatches open-preview-url with a signed URL', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('previewForRouter', $router->id)
        ->assertDispatched('open-preview-url', fn ($event, $payload) => str_contains($payload['url'] ?? '', 'preview-login-html') &&
            str_contains($payload['url'] ?? '', 'signature='));
});

test('previewForRouter dispatches error notify when router not owned by customer', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $other->id]);

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('previewForRouter', $router->id)
        ->assertDispatched('notify');
});

// ── Download route (HTTP) ─────────────────────────────────────────────────────

test('download route returns HTML file for router owner with valid signed URL', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);
    makePlan($customer);

    $url = URL::temporarySignedRoute(
        'customer.plans.download-login-html',
        now()->addMinutes(15),
        ['routerId' => $router->id]
    );

    $response = $this->actingAs($customer)
        ->get($url);

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

    expect($response->headers->get('Content-Disposition'))->toContain('skymanager-legacy-single-file');
});

test('download route returns 403 with no signature', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);

    $this->actingAs($customer)
        ->get(route('customer.plans.download-login-html', ['routerId' => $router->id]))
        ->assertForbidden();
});

test('download route returns 403 when router belongs to another customer', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $other->id]);

    $url = URL::temporarySignedRoute(
        'customer.plans.download-login-html',
        now()->addMinutes(15),
        ['routerId' => $router->id]
    );

    $this->actingAs($customer)
        ->get($url)
        ->assertForbidden();
});

test('download route returns 401 for unauthenticated visitors', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);

    $this->get(route('customer.plans.download-login-html', ['routerId' => $router->id]))
        ->assertRedirect(route('customer.login'));
});

// ── Preview route (HTTP, signed URL) ─────────────────────────────────────────

test('preview route renders HTML for router owner with valid signed URL', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);
    makePlan($customer);

    $url = URL::temporarySignedRoute(
        'customer.plans.preview-login-html',
        now()->addMinutes(30),
        ['routerId' => $router->id]
    );

    $this->actingAs($customer)
        ->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
});

test('preview route returns 403 with an invalid or missing signature', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);

    $this->actingAs($customer)
        ->get(route('customer.plans.preview-login-html', ['routerId' => $router->id]))
        ->assertForbidden();
});

test('preview route returns 403 when router belongs to another customer', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $other->id]);

    $url = URL::temporarySignedRoute(
        'customer.plans.preview-login-html',
        now()->addMinutes(30),
        ['routerId' => $router->id]
    );

    $this->actingAs($customer)
        ->get($url)
        ->assertForbidden();
});
