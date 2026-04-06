<?php

namespace App\Livewire\Customer;

use App\Models\Router;
use App\Models\User;
use App\Services\RouterOnboardingService;
use App\Services\WireguardTunnelIpAllocator;
use App\Support\WireguardProvisioning;
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

    /** WireGuard tunnel IP for ZTP/API over VPN (e.g. 10.10.0.5/32). */
    public string $wg_address = '';

    public bool $wg_auto_assign = false;

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

    #[Computed]
    public function wireguardAutoAssignOffered(): bool
    {
        return (bool) config('services.wireguard.auto_assign_router_ips', false)
            && ($this->preferred_vpn_mode === 'wireguard');
    }

    public function claimRouter(): void
    {
        $rules = [
            'name' => 'required|string|max:100',
            'mac_address' => 'nullable|string|max:17',
            'ip_address' => 'nullable|string|max:45',
            'hotspot_ssid' => 'nullable|string|max:255',
            'wan_interface' => 'nullable|string|max:32',
            'wifi_interface' => 'nullable|string|max:32',
            'hotspot_interface_custom' => 'nullable|string|max:32',
            'hotspot_gateway_custom' => 'nullable|string|max:45',
            'hotspot_network_custom' => 'nullable|string|max:64',
            'preferred_vpn_mode' => 'required|string|in:wireguard,auto,none',
            'router_model' => 'nullable|string|max:64',
            'routeros_version_hint' => 'nullable|string|max:24',
            'api_username_override' => 'nullable|string|max:64',
            'api_port_override' => 'nullable|integer|min:1|max:65535',
            'wg_address' => 'nullable|string|max:64',
        ];

        if ($this->preferred_vpn_mode === 'wireguard') {
            $autoPath = $this->wireguardAutoAssignOffered && $this->wg_auto_assign;
            if ($autoPath) {
                if (! WireguardProvisioning::isServerConfigComplete()) {
                    $this->addError('wg_auto_assign', __('Automatic tunnel IP requires full server WireGuard settings: WG_VPS_ENDPOINT, WG_VPS_PUBLIC_KEY, WG_LISTEN_PORT, and WG_API_SUBNET.'));

                    return;
                }
            } else {
                $rules['wg_address'] = ['required', 'string', 'max:64', 'regex:/^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/'];
            }
        }

        $this->validate($rules);

        $wgTunnel = trim($this->wg_address);
        if ($wgTunnel !== '' && ! str_contains($wgTunnel, '/')) {
            $wgTunnel .= '/32';
        }

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
            'wg_address' => ($this->preferred_vpn_mode === 'wireguard' && $wgTunnel !== '') ? $wgTunnel : null,
            'is_online' => false,
            'onboarding_status' => 'claimed',
            'claimed_at' => now(),
        ]);

        app(RouterOnboardingService::class)->recordClaim($router->fresh());

        $claimWarnings = [];

        if ($this->preferred_vpn_mode === 'wireguard' && $this->wireguardAutoAssignOffered && $this->wg_auto_assign && $router->wg_address === null) {
            try {
                $assigned = app(WireguardTunnelIpAllocator::class)->allocateForRouter($router->fresh());
                $router->forceFill(['wg_address' => $assigned])->save();
                $claimWarnings[] = __('WireGuard tunnel IP :ip was assigned automatically from WG_API_SUBNET.', ['ip' => $assigned]);
            } catch (\Throwable $e) {
                $claimWarnings[] = __('Could not auto-assign a WireGuard IP: :msg Set wg_address manually and save.', ['msg' => $e->getMessage()]);
            }
        }
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
            if (! WireguardProvisioning::isServerConfigComplete()) {
                $claimWarnings[] = __('Server WireGuard env is incomplete — generate the setup script only after setting WG_VPS_ENDPOINT, WG_VPS_PUBLIC_KEY, WG_LISTEN_PORT, and WG_API_SUBNET on the app.');
            } elseif (! WireguardProvisioning::isRouterWgAddressUsable($router->fresh()->wg_address)) {
                $claimWarnings[] = __('This router still needs a valid WireGuard tunnel IP (wg_address) before VPN provisioning.');
            }
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
            'wg_address', 'wg_auto_assign',
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
