<?php

namespace App\Livewire;

use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.welcome')]
#[Title('SKYmanager — Professional WiFi Hotspot Management')]
class WelcomePage extends Component
{
    public string $companyName = 'SKYmanager';

    public function mount(): void
    {
        if (auth()->check()) {
            if (auth()->user()->hasRole('customer')) {
                redirect()->route('customer.dashboard');

                return;
            }

            redirect()->route('dashboard');

            return;
        }

        $this->companyName = Setting::get('company_name', 'SKYmanager');
    }

    public function render()
    {
        return view('livewire.welcome-page', [
            'companyName' => $this->companyName,
        ]);
    }
}
