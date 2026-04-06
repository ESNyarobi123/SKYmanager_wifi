<?php

use App\Models\Customer;
use App\Models\CustomerBillingPlan;
use App\Models\Router;
use App\Services\HotspotBundleService;
use App\Services\MikrotikApiService;
use Illuminate\Support\Facades\URL;

test('hotspot bundle file rejects missing token', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();

    $this->get('/hotspot-bundle/'.$router->id.'/login.html')->assertForbidden();
});

test('hotspot bundle file serves login.html with valid token and md5.js sibling reference', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $token = $router->ensureLocalPortalToken();

    $this->get('/hotspot-bundle/'.$router->id.'/login.html?token='.rawurlencode($token))
        ->assertSuccessful()
        ->assertSee('$(mac)', false)
        ->assertSee('md5.js', false)
        ->assertSee('/api/local-portal/', false);
});

test('hotspot bundle rejects invalid token', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $router->ensureLocalPortalToken();

    $this->get('/hotspot-bundle/'.$router->id.'/login.html?token=deadbeef')
        ->assertForbidden();
});

test('hotspot bundle token is scoped to router id', function () {
    $customer = Customer::factory()->create();
    $r1 = Router::factory()->for($customer, 'user')->create();
    $r2 = Router::factory()->for($customer, 'user')->create();
    $token = $r1->ensureLocalPortalToken();

    $this->get('/hotspot-bundle/'.$r2->id.'/login.html?token='.rawurlencode($token))
        ->assertForbidden();
});

test('unknown hotspot bundle file returns 404', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $token = $router->ensureLocalPortalToken();

    $this->get('/hotspot-bundle/'.$router->id.'/evil.php?token='.rawurlencode($token))
        ->assertNotFound();
});

test('manifest json returns bundle file list', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $token = $router->ensureLocalPortalToken();

    $this->getJson('/hotspot-bundle/'.$router->id.'/manifest.json?token='.rawurlencode($token))
        ->assertSuccessful()
        ->assertJsonPath('router_id', $router->id)
        ->assertJsonStructure(['files', 'bundle_hash', 'folder_segment']);
});

test('install.rsc includes fetch commands for each bundle file', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $token = $router->ensureLocalPortalToken();

    $response = $this->get('/hotspot-bundle/'.$router->id.'/install.rsc?token='.rawurlencode($token));
    $response->assertSuccessful()->assertSee('/tool fetch', false);

    foreach (HotspotBundleService::BUNDLE_FILES as $file) {
        $response->assertSee($file, false);
    }
});

test('HotspotBundleService generates complete file set', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $bundles = app(HotspotBundleService::class);
    $files = $bundles->generateAllFiles($router, $customer);

    foreach (HotspotBundleService::BUNDLE_FILES as $name) {
        expect($files)->toHaveKey($name)
            ->and(strlen($files[$name]))->toBeGreaterThan(20);
    }
});

test('sync bundle metadata persists hash and folder name', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $router->ensureLocalPortalToken();

    app(HotspotBundleService::class)->syncBundleMetadata($router->fresh(), $customer);
    $router->refresh();

    expect($router->portal_bundle_hash)->not->toBeNull()
        ->and($router->portal_folder_name)->toBe('sky-'.$router->id)
        ->and($router->portal_generated_at)->not->toBeNull();
});

test('bundle hash changes when customer billing plans change', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $bundles = app(HotspotBundleService::class);
    $bundles->syncBundleMetadata($router, $customer);
    $h1 = $router->fresh()->portal_bundle_hash;

    CustomerBillingPlan::factory()->create(['customer_id' => $customer->id, 'is_active' => true]);

    $bundles->syncBundleMetadata($router->fresh(), $customer);
    $h2 = $router->fresh()->portal_bundle_hash;

    expect($h1)->not->toBe($h2);
});

test('full setup script uses multi-file hotspot bundle and subdirectory html-directory', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();
    $script = app(MikrotikApiService::class)->generateFullSetupScript($router->fresh());

    expect($script)->toContain('/hotspot-bundle/')
        ->and($script)->toContain('md5.js')
        ->and($script)->toContain('hotspot/sky-'.$router->id)
        ->and($script)->toContain('flash/hotspot/sky-'.$router->id)
        ->and($script)->toContain('html-directory="hotspot/sky-'.$router->id.'"');
});

test('legacy hotspot-login.html endpoint still responds', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();

    $this->get('/hotspot-login.html?router_id='.$router->id)
        ->assertSuccessful();
});

test('customer hotspot bundle overview requires authentication', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();

    $this->get(route('customer.plans.hotspot-bundle', ['routerId' => $router->id]))
        ->assertRedirect();
});

test('customer hotspot bundle overview allowed for owner', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create();

    $this->actingAs($customer)
        ->get(route('customer.plans.hotspot-bundle', ['routerId' => $router->id]))
        ->assertSuccessful()
        ->assertSee('login.html', false);
});

test('signed bundle file rejected when authenticated as different customer', function () {
    $owner = Customer::factory()->create();
    $other = Customer::factory()->create();
    $router = Router::factory()->for($owner, 'user')->create();

    $url = URL::temporarySignedRoute('customer.plans.hotspot-bundle-file', now()->addMinutes(10), [
        'routerId' => $router->id,
        'file' => 'login.html',
    ]);

    $this->actingAs($other)->get($url)->assertForbidden();
});
