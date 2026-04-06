<?php

use App\Models\Customer;
use App\Models\Router;
use App\Services\MikrotikApiService;
use App\Services\WireguardTunnelIpAllocator;
use App\Support\RouterOnboarding;
use App\Support\WireguardProvisioning;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.wireguard.vps_endpoint' => '203.0.113.50',
        'services.wireguard.vps_public_key' => 'AbCdEfGhIjKlMnOpQrStUvWxYz012345678901234567890=',
        'services.wireguard.listen_port' => 51820,
        'services.wireguard.api_subnet' => '10.10.0.0/24',
        'services.wireguard.vps_interface_name' => 'wg-sky',
        'services.wireguard.auto_assign_router_ips' => false,
        'skymanager.wireguard.vps_interface_name' => '',
    ]);
});

test('full setup script includes endpoint-port and VPS helper uses configured interface name', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create([
        'preferred_vpn_mode' => 'wireguard',
        'wg_address' => '10.10.0.44/32',
    ]);

    $script = app(MikrotikApiService::class)->generateFullSetupScript($router->fresh());

    expect($script)->toContain('endpoint-port=51820')
        ->and($script)->toContain('endpoint-address="203.0.113.50"')
        ->and($script)->toContain('persistent-keepalive=25s')
        ->and($script)->toContain('/interface wireguard peers set [find comment="SKYmanager-VPS"]')
        ->and($script)->toContain('/interface wireguard peers add interface="wg-sky"')
        ->and($script)->toContain('/interface wireguard set [find name="wg-sky"] listen-port=51820')
        ->and($script)->toContain('resolved VPS Linux WG interface (sudo wg): wg-sky')
        ->and($script)->toContain(':put ("  sudo wg set wg-sky peer " . $wgPubKey . " allowed-ips 10.10.0.44/32 persistent-keepalive 25")')
        ->and($script)->not->toContain('peer \\" . $wgPubKey')
        ->and($script)->not->toContain('sudo wg set wg0 peer')
        ->and($script)->not->toContain('default wg0');
});

test('skymanager wireguard vps interface overrides services.wireguard value', function () {
    config([
        'services.wireguard.vps_interface_name' => 'wg0',
        'skymanager.wireguard.vps_interface_name' => 'wg-sky',
    ]);

    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create([
        'preferred_vpn_mode' => 'wireguard',
        'wg_address' => '10.10.0.44/32',
    ]);

    $script = app(MikrotikApiService::class)->generateFullSetupScript($router->fresh());

    expect($script)->toContain('sudo wg set wg-sky peer')
        ->and($script)->toContain('Linux WG interface on VPS: wg-sky')
        ->and(WireguardProvisioning::vpsInterfaceName())->toBe('wg-sky');
});

test('script omits wireguard peer block when WG_VPS_ENDPOINT is missing', function () {
    config(['services.wireguard.vps_endpoint' => '']);

    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create([
        'preferred_vpn_mode' => 'wireguard',
        'wg_address' => '10.10.0.44/32',
    ]);

    $script = app(MikrotikApiService::class)->generateFullSetupScript($router->fresh());

    expect($script)->not->toContain('/interface wireguard peers add')
        ->and($script)->toContain('WG_VPS_ENDPOINT')
        ->and($router->fresh()->onboarding_status)->toBe(RouterOnboarding::ERROR);
});

test('script omits wireguard peer block when WG_VPS_PUBLIC_KEY is missing', function () {
    config(['services.wireguard.vps_public_key' => '']);

    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create([
        'preferred_vpn_mode' => 'wireguard',
        'wg_address' => '10.10.0.44/32',
    ]);

    $script = app(MikrotikApiService::class)->generateFullSetupScript($router->fresh());

    expect($script)->not->toContain('/interface wireguard peers add')
        ->and($script)->toContain('WG_VPS_PUBLIC_KEY');
});

test('script omits wireguard peer block when wg_address is missing in wireguard mode', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create([
        'preferred_vpn_mode' => 'wireguard',
        'wg_address' => null,
    ]);

    $script = app(MikrotikApiService::class)->generateFullSetupScript($router->fresh());

    expect($script)->not->toContain('/interface wireguard peers add')
        ->and($script)->toContain('wg_address');
});

test('dhcp section uses conflict guard variable', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create([
        'preferred_vpn_mode' => 'none',
        'wg_address' => null,
    ]);

    $script = app(MikrotikApiService::class)->generateFullSetupScript($router->fresh());

    expect($script)->toContain(':local skyDhcpConflict 0')
        ->and($script)->toContain('DHCP server already exists on');
});

test('default interface assumptions emit actionable warnings', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->for($customer, 'user')->create([
        'preferred_vpn_mode' => 'none',
        'wan_interface' => null,
        'wifi_interface' => null,
        'hotspot_interface' => '',
    ]);

    $script = app(MikrotikApiService::class)->generateFullSetupScript($router->fresh());

    expect($script)->toContain('defaulting to ether1')
        ->and($script)->toContain('defaulting to wlan1');
});

test('wireguard tunnel ip allocator assigns unique addresses', function () {
    config(['services.wireguard.auto_assign_router_ips' => true]);

    $customer = Customer::factory()->create();
    $a = Router::factory()->for($customer, 'user')->create(['wg_address' => null, 'preferred_vpn_mode' => 'wireguard']);
    $b = Router::factory()->for($customer, 'user')->create(['wg_address' => null, 'preferred_vpn_mode' => 'wireguard']);

    $alloc = app(WireguardTunnelIpAllocator::class);
    $ipA = $alloc->allocateForRouter($a->fresh());
    $a->update(['wg_address' => $ipA]);
    $ipB = $alloc->allocateForRouter($b->fresh());

    expect($ipA)->not->toBe($ipB)
        ->and($ipA)->toEndWith('/32')
        ->and($ipB)->toEndWith('/32');
});

test('wireguard provisioning detects missing listen port', function () {
    config(['services.wireguard.listen_port' => 0]);

    expect(WireguardProvisioning::isServerConfigComplete())->toBeFalse()
        ->and(WireguardProvisioning::missingServerEnvComponents())->toContain('WG_LISTEN_PORT (must be 1–65535)');
});
