<?php

namespace App\Livewire\Customer;

use App\Models\CustomerBillingPlan;
use App\Models\Router;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class MyPlans extends Component
{
    // ── Modal state ──────────────────────────────────────────────────────────
    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public bool $showPortalModal = false;

    public ?string $editingPlanId = null;

    public ?string $deletingPlanId = null;

    // ── Form fields ──────────────────────────────────────────────────────────
    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('required|numeric|min:0|max:999999')]
    public string $price = '';

    #[Validate('required|integer|min:1|max:525600')]
    public string $durationMinutes = '';

    #[Validate('nullable|integer|min:1|max:1048576')]
    public string $dataQuotaMb = '';

    #[Validate('nullable|integer|min:1|max:1048576')]
    public string $uploadSpeedKbps = '';

    #[Validate('nullable|integer|min:1|max:1048576')]
    public string $downloadSpeedKbps = '';

    #[Validate('nullable|string|max:500')]
    public string $description = '';

    #[Validate('boolean')]
    public bool $isActive = true;

    // ── Duration convenience fields (converted to minutes on save) ───────────
    public string $durationUnit = 'minutes';

    // ── Portal URL state ─────────────────────────────────────────────────────
    public bool $portalUrlCopied = false;

    // ── Download / Preview modal state ───────────────────────────────────────
    public bool $showDownloadModal = false;

    public bool $showPreviewModal = false;

    // ── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function customer(): User
    {
        return auth()->user();
    }

    #[Computed]
    public function plans()
    {
        return $this->customer->billingPlans()->latest()->get();
    }

    #[Computed]
    public function portalUrl(): string
    {
        return $this->customer->portalUrl();
    }

    #[Computed]
    public function editingPlan(): ?CustomerBillingPlan
    {
        if (! $this->editingPlanId) {
            return null;
        }

        return $this->customer->billingPlans()->find($this->editingPlanId);
    }

    #[Computed]
    public function customerRouters()
    {
        return Router::where('user_id', $this->customer->id)
            ->orderBy('name')
            ->get(['id', 'name', 'hotspot_ssid']);
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->editingPlanId = null;
        $this->showFormModal = true;
    }

    public function openEditModal(string $planId): void
    {
        $plan = $this->customer->billingPlans()->findOrFail($planId);
        $this->authorize('update', $plan);

        $this->editingPlanId = $planId;
        $this->name = $plan->name;
        $this->price = (string) $plan->price;
        $this->description = $plan->description ?? '';
        $this->isActive = $plan->is_active;
        $this->dataQuotaMb = $plan->data_quota_mb ? (string) $plan->data_quota_mb : '';
        $this->uploadSpeedKbps = $plan->upload_speed_kbps ? (string) $plan->upload_speed_kbps : '';
        $this->downloadSpeedKbps = $plan->download_speed_kbps ? (string) $plan->download_speed_kbps : '';

        // Determine unit from stored minutes
        $minutes = $plan->duration_minutes;
        if ($minutes >= 1440 && $minutes % 1440 === 0) {
            $this->durationUnit = 'days';
            $this->durationMinutes = (string) ($minutes / 1440);
        } elseif ($minutes >= 60 && $minutes % 60 === 0) {
            $this->durationUnit = 'hours';
            $this->durationMinutes = (string) ($minutes / 60);
        } else {
            $this->durationUnit = 'minutes';
            $this->durationMinutes = (string) $minutes;
        }

        $this->showFormModal = true;
    }

    public function savePlan(): void
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0|max:999999',
            'durationMinutes' => 'required|integer|min:1',
            'dataQuotaMb' => 'nullable|integer|min:1|max:1048576',
            'uploadSpeedKbps' => 'nullable|integer|min:1|max:1048576',
            'downloadSpeedKbps' => 'nullable|integer|min:1|max:1048576',
            'description' => 'nullable|string|max:500',
            'isActive' => 'boolean',
        ]);

        $storedMinutes = $this->resolveMinutes();

        $data = [
            'name' => $this->name,
            'price' => $this->price,
            'duration_minutes' => $storedMinutes,
            'data_quota_mb' => $this->dataQuotaMb ?: null,
            'upload_speed_kbps' => $this->uploadSpeedKbps ?: null,
            'download_speed_kbps' => $this->downloadSpeedKbps ?: null,
            'description' => $this->description ?: null,
            'is_active' => $this->isActive,
        ];

        if ($this->editingPlanId) {
            $plan = $this->customer->billingPlans()->findOrFail($this->editingPlanId);
            $this->authorize('update', $plan);
            $plan->update($data);
            $message = __('Plan updated successfully.');
        } else {
            $this->authorize('create', CustomerBillingPlan::class);
            $this->customer->billingPlans()->create($data);
            $message = __('Plan created successfully.');
        }

        unset($this->plans);
        $this->showFormModal = false;
        $this->resetForm();
        $this->dispatch('notify', message: $message, type: 'success');
    }

    public function confirmDelete(string $planId): void
    {
        $plan = $this->customer->billingPlans()->findOrFail($planId);
        $this->authorize('delete', $plan);
        $this->deletingPlanId = $planId;
        $this->showDeleteModal = true;
    }

    public function deletePlan(): void
    {
        if (! $this->deletingPlanId) {
            return;
        }

        $plan = $this->customer->billingPlans()->findOrFail($this->deletingPlanId);
        $this->authorize('delete', $plan);
        $plan->delete();

        unset($this->plans);
        $this->showDeleteModal = false;
        $this->deletingPlanId = null;
        $this->dispatch('notify', message: __('Plan deleted.'), type: 'success');
    }

    public function toggleActive(string $planId): void
    {
        $plan = $this->customer->billingPlans()->findOrFail($planId);
        $this->authorize('update', $plan);
        $plan->update(['is_active' => ! $plan->is_active]);
        unset($this->plans);
    }

    public function regeneratePortalSubdomain(): void
    {
        $this->customer->generatePortalSubdomain();
        unset($this->customer, $this->portalUrl);
        $this->portalUrlCopied = false;
        $this->dispatch('notify', message: __('New portal URL generated.'), type: 'success');
    }

    /**
     * Open the "Generate Standalone Login.html" download modal.
     * If the customer has exactly one router, redirect directly to the download.
     * If they have multiple, show the router-selection modal.
     */
    public function openDownloadModal(): void
    {
        $routers = $this->customerRouters;

        if ($routers->isEmpty()) {
            $this->dispatch('notify', message: __('You have no routers yet. Claim a router first.'), type: 'warning');

            return;
        }

        if ($routers->count() === 1) {
            $this->downloadForRouter($routers->first()->id);

            return;
        }

        $this->showDownloadModal = true;
    }

    /**
     * Generate a signed download URL (valid 15 min) and redirect to it.
     * Signed URL prevents link sharing and adds a time-based expiry.
     */
    public function downloadForRouter(string $routerId): void
    {
        $router = $this->customerRouters->firstWhere('id', $routerId);

        if (! $router) {
            $this->dispatch('notify', message: __('Router not found.'), type: 'error');

            return;
        }

        $url = URL::temporarySignedRoute(
            'customer.plans.download-login-html',
            now()->addMinutes(15),
            ['routerId' => $routerId]
        );

        $this->showDownloadModal = false;
        $this->redirect($url);
    }

    /**
     * Open the "Preview in Browser" modal for router selection.
     * If the customer has exactly one router, open the preview directly.
     */
    public function openPreviewModal(): void
    {
        $routers = $this->customerRouters;

        if ($routers->isEmpty()) {
            $this->dispatch('notify', message: __('You have no routers yet. Claim a router first.'), type: 'warning');

            return;
        }

        if ($routers->count() === 1) {
            $this->previewForRouter($routers->first()->id);

            return;
        }

        $this->showPreviewModal = true;
    }

    /**
     * Generate a signed preview URL (valid 30 min) and dispatch a browser event
     * so Alpine.js opens it in a new tab — Livewire cannot open new tabs directly.
     */
    public function previewForRouter(string $routerId): void
    {
        $router = $this->customerRouters->firstWhere('id', $routerId);

        if (! $router) {
            $this->dispatch('notify', message: __('Router not found.'), type: 'error');

            return;
        }

        $url = URL::temporarySignedRoute(
            'customer.plans.preview-login-html',
            now()->addMinutes(15),
            ['routerId' => $routerId]
        );

        $this->showPreviewModal = false;
        $this->dispatch('open-preview-url', url: $url);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function resolveMinutes(): int
    {
        $value = (int) $this->durationMinutes;

        return match ($this->durationUnit) {
            'hours' => $value * 60,
            'days' => $value * 1440,
            default => $value,
        };
    }

    protected function resetForm(): void
    {
        $this->name = '';
        $this->price = '';
        $this->durationMinutes = '';
        $this->durationUnit = 'minutes';
        $this->dataQuotaMb = '';
        $this->uploadSpeedKbps = '';
        $this->downloadSpeedKbps = '';
        $this->description = '';
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.customer.my-plans');
    }
}
