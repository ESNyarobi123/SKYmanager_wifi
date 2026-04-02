<?php

use App\Models\BillingPlan;
use App\Models\Router;
use App\Services\MikrotikApiService;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public bool $showModal = false;

    public ?string $editingId = null;

    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('required|numeric|min:0')]
    public string $price = '';

    #[Validate('required|integer|min:1')]
    public int $durationMinutes = 60;

    #[Validate('required|integer|min:1|max:1000')]
    public int $uploadLimit = 5;

    #[Validate('required|integer|min:1|max:1000')]
    public int $downloadLimit = 10;

    #[Validate('nullable|string|max:500')]
    public string $description = '';

    #[Validate('nullable|integer|min:1|max:102400')]
    public ?int $dataQuotaMb = null;

    public bool $isActive = true;

    public function plans()
    {
        return BillingPlan::latest()->get();
    }

    public function openModal(?string $id = null): void
    {
        $this->reset(['name', 'price', 'durationMinutes', 'uploadLimit', 'downloadLimit', 'description', 'dataQuotaMb', 'isActive']);
        $this->durationMinutes = 60;
        $this->uploadLimit = 5;
        $this->downloadLimit = 10;
        $this->isActive = true;
        $this->dataQuotaMb = null;
        $this->editingId = $id;

        if ($id) {
            $plan = BillingPlan::findOrFail($id);
            $this->name = $plan->name;
            $this->price = $plan->price;
            $this->durationMinutes = $plan->duration_minutes;
            $this->uploadLimit = $plan->upload_limit;
            $this->downloadLimit = $plan->download_limit;
            $this->description = $plan->description ?? '';
            $this->dataQuotaMb = $plan->data_quota_mb;
            $this->isActive = $plan->is_active;
        }

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'price' => $this->price,
            'duration_minutes' => $this->durationMinutes,
            'upload_limit' => $this->uploadLimit,
            'download_limit' => $this->downloadLimit,
            'description' => $this->description ?: null,
            'data_quota_mb' => $this->dataQuotaMb ?: null,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId) {
            BillingPlan::findOrFail($this->editingId)->update($data);
            session()->flash('status', 'Plan updated.');
        } else {
            BillingPlan::create($data);
            session()->flash('status', 'Plan created.');
        }

        $this->showModal = false;
        $this->syncProfilesWithRouters();
    }

    private function syncProfilesWithRouters(): void
    {
        $plans = BillingPlan::where('is_active', true)->get();
        $routers = Router::where('is_online', true)->get();
        $mikrotik = app(MikrotikApiService::class);

        foreach ($routers as $router) {
            try {
                $mikrotik->connect($router);
                foreach ($plans as $plan) {
                    try {
                        $mikrotik->syncHotspotProfile($plan->name, $plan->upload_limit, $plan->download_limit);
                    } catch (\Exception) {
                    }
                }
                $mikrotik->disconnect();
            } catch (\Exception) {
            }
        }
    }

    public function delete(string $id): void
    {
        BillingPlan::findOrFail($id)->delete();
        session()->flash('status', 'Plan deleted.');
    }

    public function toggleActive(string $id): void
    {
        $plan = BillingPlan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);
    }
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Billing Plans</h1>
        <flux:button wire:click="openModal" icon="plus" variant="primary">Add Plan</flux:button>
    </div>

    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 dark:bg-emerald-800/10 dark:border-emerald-900 dark:text-emerald-500 mb-4" role="alert"><div class="flex gap-x-3"><x-lucide name="check-circle" class="size-4 shrink-0 mt-0.5"/><p class="text-sm">{{ session('status') }}</p></div></div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->plans() as $plan)
            <flux:card class="flex flex-col gap-3">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">{{ $plan->name }}</h2>
                        <span class="text-2xl font-bold text-purple-700 dark:text-purple-400">TZS {{ number_format($plan->price, 0) }}</span>
                    </div>
                    <flux:badge :color="$plan->is_active ? 'green' : 'zinc'" size="sm">
                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                    </flux:badge>
                </div>

                @if ($plan->description)
                    <flux:text class="text-sm text-zinc-500">{{ $plan->description }}</flux:text>
                @endif

                <div class="grid grid-cols-4 gap-2 text-sm text-center">
                    <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-2">
                        <div class="font-semibold">{{ $plan->duration_minutes >= 60 ? round($plan->duration_minutes / 60, 1).'h' : $plan->duration_minutes.'m' }}</div>
                        <div class="text-xs text-zinc-400">Duration</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-2">
                        <div class="font-semibold">{{ $plan->upload_limit }}M</div>
                        <div class="text-xs text-zinc-400">Upload</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-2">
                        <div class="font-semibold">{{ $plan->download_limit }}M</div>
                        <div class="text-xs text-zinc-400">Download</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-2">
                        <div class="font-semibold">{{ $plan->data_quota_mb ? $plan->data_quota_mb.' MB' : '∞' }}</div>
                        <div class="text-xs text-zinc-400">Quota</div>
                    </div>
                </div>

                <div class="flex gap-2 pt-1">
                    <flux:button size="sm" icon="pencil" class="flex-1" wire:click="openModal('{{ $plan->id }}')">Edit</flux:button>
                    <flux:button size="sm" :icon="$plan->is_active ? 'eye-slash' : 'eye'" wire:click="toggleActive('{{ $plan->id }}')"></flux:button>
                    <flux:button size="sm" icon="trash" variant="danger"
                        wire:click="delete('{{ $plan->id }}')"
                        wire:confirm="Delete this plan?"></flux:button>
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">{{ $editingId ? 'Edit Plan' : 'Add Plan' }}</h2>
            <div class="flex flex-col gap-4">
                <flux:input wire:model="name" label="Plan Name" placeholder="Basic 1 Hour" />
                <flux:input wire:model="price" label="Price (TZS)" type="number" min="0" step="0.01" />
                <flux:input wire:model="durationMinutes" label="Duration (minutes)" type="number" min="1" />
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="uploadLimit" label="Upload Limit (Mbps)" type="number" min="1" />
                    <flux:input wire:model="downloadLimit" label="Download Limit (Mbps)" type="number" min="1" />
                </div>
                <flux:textarea wire:model="description" label="Description" rows="2" placeholder="Optional description..." />
                <flux:input wire:model="dataQuotaMb" label="Data Quota (MB, blank = unlimited)" type="number" min="1" placeholder="e.g. 1024" />
                <flux:checkbox wire:model="isActive" label="Active" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="save" variant="primary">Save</flux:button>
            </div>
        </div>
    </flux:modal>
</div>