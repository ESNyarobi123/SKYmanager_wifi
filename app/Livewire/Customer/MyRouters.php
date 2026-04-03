<?php

namespace App\Livewire\Customer;

use App\Models\Router;
use App\Models\User;
use App\Services\MikrotikApiService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.customer')]
class MyRouters extends Component
{
    public ?string $selectedRouterId = null;

    public bool $showDetailModal = false;

    public bool $showScriptModal = false;

    public bool $showRenameModal = false;

    public string $generatedScript = '';

    public bool $scriptCopied = false;

    #[Validate('required|string|max:100')]
    public string $newName = '';

    #[Computed]
    public function customer(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function routers()
    {
        return $this->customer->routers()
            ->with(['subscriptions' => fn ($q) => $q->where('status', 'active')->with('plan')])
            ->latest()
            ->get();
    }

    #[Computed]
    public function selectedRouter(): ?Router
    {
        if (! $this->selectedRouterId) {
            return null;
        }

        return $this->customer->routers()
            ->with(['subscriptions.plan', 'subscriptions.latestPayment'])
            ->find($this->selectedRouterId);
    }

    public function viewRouter(string $routerId): void
    {
        $this->selectedRouterId = $routerId;
        $this->showDetailModal = true;
    }

    public function openScriptModal(string $routerId): void
    {
        $this->selectedRouterId = $routerId;
        $this->scriptCopied = false;

        $router = $this->customer->routers()->find($routerId);
        if (! $router) {
            return;
        }

        $this->generatedScript = app(MikrotikApiService::class)
            ->generateFullSetupScript($router);

        $this->showScriptModal = true;
    }

    public function openRenameModal(string $routerId): void
    {
        $router = $this->customer->routers()->find($routerId);
        if (! $router) {
            return;
        }

        $this->selectedRouterId = $routerId;
        $this->newName = $router->name;
        $this->showRenameModal = true;
    }

    public function renameRouter(): void
    {
        $this->validate(['newName' => 'required|string|max:100']);

        $router = $this->customer->routers()->findOrFail($this->selectedRouterId);
        $router->update(['name' => $this->newName]);

        $this->showRenameModal = false;
        $this->selectedRouterId = null;
        $this->newName = '';
        unset($this->routers);

        $this->dispatch('notify', message: __('Router renamed successfully.'), type: 'success');
    }

    public function closeModal(): void
    {
        $this->showScriptModal = false;
        $this->generatedScript = '';
    }

    public function markScriptPasted(): void
    {
        $this->showScriptModal = false;
        $this->generatedScript = '';
        $this->dispatch('notify', message: __('Script applied! Router will connect shortly.'), type: 'success');
    }

    #[On('router-claimed')]
    public function refreshRouters(): void
    {
        unset($this->routers);
    }

    public function render()
    {
        return view('livewire.customer.my-routers');
    }
}
