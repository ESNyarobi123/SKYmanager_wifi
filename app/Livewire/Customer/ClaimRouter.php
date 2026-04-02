<?php

namespace App\Livewire\Customer;

use App\Models\Router;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.customer')]
class ClaimRouter extends Component
{
    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('nullable|string|max:17')]
    public string $mac_address = '';

    #[Validate('nullable|string|max:45')]
    public string $ip_address = '';

    #[Validate('nullable|string|max:255')]
    public string $hotspot_ssid = '';

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

        $router = Router::create([
            'user_id' => $this->customer->id,
            'name' => $this->name,
            'mac_address' => $this->mac_address ?: null,
            'ip_address' => $this->ip_address ?: '0.0.0.0',
            'api_port' => 8728,
            'api_username' => 'sky-api',
            'api_password' => '',
            'hotspot_ssid' => $this->hotspot_ssid ?: 'WIFI',
            'hotspot_interface' => 'bridge',
            'hotspot_gateway' => '192.168.88.1',
            'hotspot_network' => '192.168.88.0/24',
            'is_online' => false,
        ]);

        $this->claimed = true;
        $this->successMessage = "Router \"{$router->name}\" has been added to your account!";

        $this->reset(['name', 'mac_address', 'ip_address', 'hotspot_ssid']);

        $this->dispatch('router-claimed');
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->claimed = false;
    }

    public function render()
    {
        return view('livewire.customer.claim-router');
    }
}
