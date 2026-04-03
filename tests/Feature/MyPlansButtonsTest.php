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

// ── "Generate Login.html" / openDownloadModal ─────────────────────────────────

test('Generate Login.html shows warning notify when customer has no routers', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openDownloadModal')
        ->assertDispatched('notify');
});

test('Generate Login.html redirects to a signed URL when customer has exactly one router', function () {
    $customer = Customer::factory()->create();
    Router::factory()->create(['user_id' => $customer->id]);

    Livewire::actingAs($customer)
        ->test(MyPlans::class)
        ->call('openDownloadModal')
        ->assertRedirect();
});

test('Generate Login.html opens router-selection modal when customer has multiple routers', function () {
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

    $this->actingAs($customer)
        ->get($url)
        ->assertOk()
        ->assertHeader('Content-Disposition')
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
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
