<?php

namespace App\Livewire\Customer;

use App\Models\Router;
use App\Models\User;
use App\Services\HotspotBundleService;
use App\Services\MikrotikApiService;
use App\Services\RouterOnboardingService;
use App\Support\RouterOnboarding;
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
        $this->openScriptModalInternal($routerId, false);
    }

    public function openScriptModalWithNewApiPassword(string $routerId): void
    {
        $this->openScriptModalInternal($routerId, true);
    }

    private function openScriptModalInternal(string $routerId, bool $rotateCredentials): void
    {
        $this->selectedRouterId = $routerId;
        $this->scriptCopied = false;

        $router = $this->customer->routers()->find($routerId);
        if (! $router) {
            return;
        }

        $this->generatedScript = app(MikrotikApiService::class)
            ->generateFullSetupScript($router, $rotateCredentials);

        app(RouterOnboardingService::class)->recordScriptDownloaded($router->fresh());

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

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedRouterId = null;
    }

    public function markScriptPasted(): void
    {
        if ($this->selectedRouterId) {
            $router = $this->customer->routers()->find($this->selectedRouterId);
            if ($router) {
                app(RouterOnboardingService::class)->markScriptAppliedPending($router);
            }
        }

        $this->showScriptModal = false;
        $this->generatedScript = '';
        $this->dispatch('notify', message: __('Marked as pasted. Run a health check from your admin tools when the router finishes applying the script.'), type: 'success');
    }

    /**
     * @return 'green'|'amber'|'red'|'zinc'
     */
    public function onboardingBadgeVariant(string $status): string
    {
        return match ($status) {
            RouterOnboarding::READY => 'green',
            RouterOnboarding::TUNNEL_OK, RouterOnboarding::API_OK, RouterOnboarding::PORTAL_OK => 'green',
            RouterOnboarding::DEGRADED, RouterOnboarding::PORTAL_PENDING, RouterOnboarding::TUNNEL_PENDING, RouterOnboarding::API_PENDING => 'amber',
            RouterOnboarding::ERROR, RouterOnboarding::CRED_MISMATCH, RouterOnboarding::OFFLINE, RouterOnboarding::BUNDLE_MISMATCH => 'red',
            default => 'zinc',
        };
    }

    public function regeneratePortalBundle(string $routerId): void
    {
        $router = $this->customer->routers()->find($routerId);

        if (! $router) {
            $this->dispatch('notify', message: __('Router not found.'), type: 'error');

            return;
        }

        $router->ensureLocalPortalToken();
        app(HotspotBundleService::class)->syncBundleMetadata($router->fresh(), $this->customer);
        unset($this->routers);

        $this->dispatch('notify', message: __('Hotspot bundle metadata refreshed. Regenerate the setup script to push files to MikroTik.'), type: 'success');
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
