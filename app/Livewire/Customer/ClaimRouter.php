<?php

namespace App\Livewire\Customer;

use App\Models\Router;
use App\Models\User;
use App\Services\RouterOnboardingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.customer')]
class ClaimRouter extends Component
{
    public bool $showAdvanced = false;

    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('nullable|string|max:17')]
    public string $mac_address = '';

    #[Validate('nullable|string|max:45')]
    public string $ip_address = '';

    #[Validate('nullable|string|max:255')]
    public string $hotspot_ssid = '';

    #[Validate('nullable|string|max:32')]
    public string $wan_interface = '';

    #[Validate('nullable|string|max:32')]
    public string $wifi_interface = '';

    #[Validate('nullable|string|max:32')]
    public string $hotspot_interface_custom = '';

    #[Validate('nullable|string|max:45')]
    public string $hotspot_gateway_custom = '';

    #[Validate('nullable|string|max:64')]
    public string $hotspot_network_custom = '';

    #[Validate('required|string|in:wireguard,auto,none')]
    public string $preferred_vpn_mode = 'wireguard';

    #[Validate('nullable|string|max:64')]
    public string $router_model = '';

    #[Validate('nullable|string|max:24')]
    public string $routeros_version_hint = '';

    public bool $use_default_network_settings = true;

    #[Validate('nullable|string|max:64')]
    public string $api_username_override = '';

    #[Validate('nullable|integer|min:1|max:65535')]
    public ?int $api_port_override = null;

    public bool $claimed = false;

    public string $successMessage = '';

    #[Computed]
    public function customer(): User
    {
        return Auth::user();
    }

    public function claimRouter(): void
    {
        $this->validate();

        $hotspotInterface = $this->use_default_network_settings
            ? 'bridge'
            : ($this->hotspot_interface_custom ?: 'bridge');

        $hotspotGateway = $this->use_default_network_settings
            ? '192.168.88.1'
            : ($this->hotspot_gateway_custom ?: '192.168.88.1');

        $hotspotNetwork = $this->use_default_network_settings
            ? '192.168.88.0/24'
            : ($this->hotspot_network_custom ?: '192.168.88.0/24');

        $router = Router::create([
            'user_id' => $this->customer->id,
            'name' => $this->name,
            'mac_address' => $this->mac_address ?: null,
            'ip_address' => $this->ip_address ?: '0.0.0.0',
            'api_port' => $this->api_port_override ?: 8728,
            'api_username' => $this->api_username_override ?: 'sky-api',
            'api_password' => '',
            'api_credential_version' => 0,
            'hotspot_ssid' => $this->hotspot_ssid ?: 'WIFI',
            'hotspot_interface' => $hotspotInterface,
            'wan_interface' => $this->wan_interface ?: null,
            'wifi_interface' => $this->wifi_interface ?: null,
            'preferred_vpn_mode' => $this->preferred_vpn_mode,
            'router_model' => $this->router_model ?: null,
            'routeros_version_hint' => $this->routeros_version_hint ?: null,
            'use_default_network_settings' => $this->use_default_network_settings,
            'hotspot_gateway' => $hotspotGateway,
            'hotspot_network' => $hotspotNetwork,
            'is_online' => false,
            'onboarding_status' => 'claimed',
            'claimed_at' => now(),
        ]);

        app(RouterOnboardingService::class)->recordClaim($router->fresh());

        $claimWarnings = [];
        if ($this->use_default_network_settings) {
            $claimWarnings[] = __('Default LAN (192.168.88.x / bridge) is assumed — open Advanced if your MikroTik uses a different layout.');
        }
        if ($this->wan_interface === '') {
            $claimWarnings[] = __('WAN interface not set — the setup script will assume ether1 for NAT.');
        }
        if ($this->wifi_interface === '') {
            $claimWarnings[] = __('WiFi interface not set — the setup script will assume wlan1 for the bridge port.');
        }
        if ($this->preferred_vpn_mode === 'wireguard') {
            $claimWarnings[] = __('WireGuard mode is selected — ensure the server WG settings and this router’s wg_address are complete before expecting VPN connectivity.');
        }

        $router->refresh();
        $existing = $router->onboarding_warnings ?? [];
        $existing['claim'] = $claimWarnings;
        $router->forceFill(['onboarding_warnings' => $existing])->save();

        $this->claimed = true;
        $this->successMessage = __('Router ":name" is registered. It is not online yet — generate the setup script from My Routers and run a health check when finished.', ['name' => $router->name]);

        $this->reset([
            'name', 'mac_address', 'ip_address', 'hotspot_ssid',
            'wan_interface', 'wifi_interface', 'hotspot_interface_custom',
            'hotspot_gateway_custom', 'hotspot_network_custom',
            'router_model', 'routeros_version_hint', 'api_username_override',
        ]);
        $this->preferred_vpn_mode = 'wireguard';
        $this->use_default_network_settings = true;
        $this->api_port_override = null;
        $this->showAdvanced = false;

        $this->dispatch('router-claimed');
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->claimed = false;
        $this->preferred_vpn_mode = 'wireguard';
        $this->use_default_network_settings = true;
        $this->showAdvanced = false;
    }

    public function render()
    {
        return view('livewire.customer.claim-router');
    }
}
