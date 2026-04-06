<?php

use App\Livewire\Customer\ClaimRouter;
use App\Models\Customer;
use App\Models\Router;
use App\Services\MikrotikApiService;
use App\Services\RouterCredentialSyncService;
use App\Services\RouterHealthService;
use App\Services\RouterOnboardingService;
use App\Support\RouterOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('claim router livewire stores router as claimed with default assumption warnings', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(ClaimRouter::class)
        ->set('name', 'Branch Office')
        ->set('hotspot_ssid', 'GuestWiFi')
        ->set('wg_address', '10.10.0.99/32')
        ->call('claimRouter')
        ->assertSet('claimed', true);

    $router = Router::where('user_id', $customer->id)->sole();

    expect($router->onboarding_status)->toBe(RouterOnboarding::CLAIMED)
        ->and($router->claimed_at)->not->toBeNull()
        ->and($router->onboarding_warnings['claim'] ?? [])->not->toBeEmpty();
});

test('claim router wireguard mode requires wg_address when auto assign is off', function () {
    config(['services.wireguard.auto_assign_router_ips' => false]);

    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(ClaimRouter::class)
        ->set('name', 'WG Test')
        ->set('preferred_vpn_mode', 'wireguard')
        ->set('wg_address', '')
        ->call('claimRouter')
        ->assertHasErrors('wg_address');
});

test('claim router persists wg_address for wireguard mode', function () {
    config(['services.wireguard.auto_assign_router_ips' => false]);

    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(ClaimRouter::class)
        ->set('name', 'WG Branch')
        ->set('wg_address', '10.10.0.77/32')
        ->call('claimRouter')
        ->assertSet('claimed', true);

    expect(Router::where('user_id', $customer->id)->sole()->wg_address)->toBe('10.10.0.77/32');
});

test('claim router advanced fields persist on router', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(ClaimRouter::class)
        ->set('name', 'Advanced')
        ->set('showAdvanced', true)
        ->set('wan_interface', 'ether2')
        ->set('wifi_interface', 'wlan2')
        ->set('use_default_network_settings', false)
        ->set('hotspot_interface_custom', 'bridge-lan')
        ->set('hotspot_gateway_custom', '10.0.0.1')
        ->set('hotspot_network_custom', '10.0.0.0/24')
        ->set('preferred_vpn_mode', 'none')
        ->set('wg_address', '')
        ->set('router_model', 'hAP ax³')
        ->set('api_username_override', 'sky-api-2')
        ->set('api_port_override', 8729)
        ->call('claimRouter');

    $router = Router::where('user_id', $customer->id)->sole();

    expect($router->wan_interface)->toBe('ether2')
        ->and($router->wifi_interface)->toBe('wlan2')
        ->and($router->hotspot_interface)->toBe('bridge-lan')
        ->and($router->hotspot_gateway)->toBe('10.0.0.1')
        ->and($router->hotspot_network)->toBe('10.0.0.0/24')
        ->and($router->preferred_vpn_mode)->toBe('none')
        ->and($router->router_model)->toBe('hAP ax³')
        ->and($router->api_username)->toBe('sky-api-2')
        ->and($router->api_port)->toBe(8729);
});

test('full setup script sets error onboarding when wireguard required but server env incomplete', function () {
    config([
        'services.wireguard.vps_endpoint' => '',
        'services.wireguard.vps_public_key' => '',
    ]);

    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create([
        'preferred_vpn_mode' => 'wireguard',
        'wg_address' => '10.10.0.9/32',
    ]);

    app(MikrotikApiService::class)->generateFullSetupScript($router->fresh());

    $router->refresh();

    expect($router->onboarding_status)->toBe(RouterOnboarding::ERROR)
        ->and($router->last_error_code)->toBe('wg_required_missing');
});

test('record script downloaded preserves error onboarding status', function () {
    $router = Router::factory()->create([
        'user_id' => Customer::factory()->create()->id,
        'onboarding_status' => RouterOnboarding::ERROR,
        'last_error_code' => 'wg_required_missing',
    ]);

    app(RouterOnboardingService::class)->recordScriptDownloaded($router->fresh());

    expect($router->fresh()->onboarding_status)->toBe(RouterOnboarding::ERROR)
        ->and($router->fresh()->script_downloaded_at)->not->toBeNull();
});

test('health evaluation flags credential mismatch from router state', function () {
    $router = Router::factory()->create([
        'user_id' => Customer::factory()->create()->id,
        'credential_mismatch_suspected' => true,
        'last_api_success_at' => null,
    ]);

    $report = app(RouterHealthService::class)->evaluate($router, false);

    expect($report['api']['code'])->toBe('cred_mismatch');
});

test('rotate ztp password bumps credential version', function () {
    $router = Router::factory()->create([
        'user_id' => Customer::factory()->create()->id,
        'api_credential_version' => 3,
        'ztp_api_password' => 'old-secret',
    ]);

    app(RouterCredentialSyncService::class)->rotateZtpPassword($router->fresh());

    $router->refresh();

    expect($router->api_credential_version)->toBe(4)
        ->and($router->ztp_api_password)->not->toBe('old-secret')
        ->and($router->credential_mismatch_suspected)->toBeFalse();
});

test('mark script applied pending sets script pending status', function () {
    $router = Router::factory()->create([
        'user_id' => Customer::factory()->create()->id,
        'onboarding_status' => RouterOnboarding::SCRIPT_DOWNLOADED,
    ]);

    app(RouterOnboardingService::class)->markScriptAppliedPending($router->fresh());

    expect($router->fresh()->onboarding_status)->toBe(RouterOnboarding::SCRIPT_PENDING)
        ->and($router->fresh()->script_applied_at)->not->toBeNull();
});

test('legacy claimed router without script stays suggested claimed until script exists', function () {
    $router = Router::factory()->create([
        'user_id' => Customer::factory()->create()->id,
        'onboarding_status' => RouterOnboarding::CLAIMED,
        'script_generated_at' => null,
        'preferred_vpn_mode' => 'none',
    ]);

    $suggested = app(RouterHealthService::class)->evaluate($router, false)['suggested_onboarding_status'];

    expect($suggested)->toBe(RouterOnboarding::CLAIMED);
});

test('claimed router in wireguard mode without server env yields error suggestion', function () {
    config([
        'services.wireguard.vps_endpoint' => '',
        'services.wireguard.vps_public_key' => '',
    ]);

    $router = Router::factory()->create([
        'user_id' => Customer::factory()->create()->id,
        'onboarding_status' => RouterOnboarding::CLAIMED,
        'script_generated_at' => null,
        'preferred_vpn_mode' => 'wireguard',
    ]);

    $suggested = app(RouterHealthService::class)->evaluate($router, false)['suggested_onboarding_status'];

    expect($suggested)->toBe(RouterOnboarding::ERROR);
});

test('wireguard mode with complete server env but missing wg_address yields error suggestion', function () {
    config([
        'services.wireguard.vps_endpoint' => '203.0.113.50',
        'services.wireguard.vps_public_key' => 'dGVzdGtleQ==',
        'services.wireguard.listen_port' => 51820,
        'services.wireguard.api_subnet' => '10.10.0.0/24',
    ]);

    $router = Router::factory()->create([
        'user_id' => Customer::factory()->create()->id,
        'onboarding_status' => RouterOnboarding::CLAIMED,
        'script_generated_at' => null,
        'preferred_vpn_mode' => 'wireguard',
        'wg_address' => null,
    ]);

    $report = app(RouterHealthService::class)->evaluate($router, false);

    expect($report['suggested_onboarding_status'])->toBe(RouterOnboarding::ERROR)
        ->and($report['tunnel']['code'])->toBe('wg_address_missing');
});

test('mark script applied artisan command', function () {
    $router = Router::factory()->create([
        'user_id' => Customer::factory()->create()->id,
        'onboarding_status' => RouterOnboarding::SCRIPT_GENERATED,
    ]);

    $this->artisan('app:mark-script-applied', ['router' => $router->id])
        ->assertSuccessful();

    expect($router->fresh()->onboarding_status)->toBe(RouterOnboarding::SCRIPT_PENDING);
});
