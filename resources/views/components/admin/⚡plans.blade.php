<?php

use App\Models\BillingPlan;
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

    public bool $isActive = true;

    public function plans()
    {
        return BillingPlan::latest()->get();
    }

    public function openModal(?string $id = null): void
    {
        $this->reset(['name', 'price', 'durationMinutes', 'uploadLimit', 'downloadLimit', 'description', 'isActive']);
        $this->durationMinutes = 60;
        $this->uploadLimit = 5;
        $this->downloadLimit = 10;
        $this->isActive = true;
        $this->editingId = $id;

        if ($id) {
            $plan = BillingPlan::findOrFail($id);
            $this->name = $plan->name;
            $this->price = $plan->price;
            $this->durationMinutes = $plan->duration_minutes;
            $this->uploadLimit = $plan->upload_limit;
            $this->downloadLimit = $plan->download_limit;
            $this->description = $plan->description ?? '';
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
        <flux:heading size="xl">Billing Plans</flux:heading>
        <flux:button wire:click="openModal" icon="plus" variant="primary">Add Plan</flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->plans() as $plan)
            <flux:card class="flex flex-col gap-3">
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                        <flux:text class="text-2xl font-bold text-purple-700">TZS {{ number_format($plan->price, 0) }}</flux:text>
                    </div>
                    <flux:badge :color="$plan->is_active ? 'green' : 'zinc'" size="sm">
                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                    </flux:badge>
                </div>

                @if ($plan->description)
                    <flux:text class="text-sm text-zinc-500">{{ $plan->description }}</flux:text>
                @endif

                <div class="grid grid-cols-3 gap-2 text-sm text-center">
                    <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-2">
                        <div class="font-semibold">{{ $plan->duration_minutes >= 60 ? round($plan->duration_minutes / 60, 1).'h' : $plan->duration_minutes.'m' }}</div>
                        <div class="text-xs text-zinc-400">Duration</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-2">
                        <div class="font-semibold">{{ $plan->upload_limit }} Mbps</div>
                        <div class="text-xs text-zinc-400">Upload</div>
                    </div>
                    <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-2">
                        <div class="font-semibold">{{ $plan->download_limit }} Mbps</div>
                        <div class="text-xs text-zinc-400">Download</div>
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
            <flux:heading size="lg">{{ $editingId ? 'Edit Plan' : 'Add Plan' }}</flux:heading>
            <div class="flex flex-col gap-4">
                <flux:input wire:model="name" label="Plan Name" placeholder="Basic 1 Hour" />
                <flux:input wire:model="price" label="Price (TZS)" type="number" min="0" step="0.01" />
                <flux:input wire:model="durationMinutes" label="Duration (minutes)" type="number" min="1" />
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="uploadLimit" label="Upload Limit (Mbps)" type="number" min="1" />
                    <flux:input wire:model="downloadLimit" label="Download Limit (Mbps)" type="number" min="1" />
                </div>
                <flux:textarea wire:model="description" label="Description" rows="2" placeholder="Optional description..." />
                <flux:checkbox wire:model="isActive" label="Active" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="save" variant="primary">Save</flux:button>
            </div>
        </div>
    </flux:modal>
</div>