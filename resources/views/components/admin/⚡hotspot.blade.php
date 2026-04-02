<?php

use App\Models\BillingPlan;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\WifiUser;
use App\Services\MikrotikApiService;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = 'all';

    // ----- Add MAC Binding modal -----
    public bool $showBindModal = false;

    public ?string $bindingUserId = null;

    #[Validate('required|regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/')]
    public string $bindMac = '';

    #[Validate('required|string|max:20')]
    public string $bindPhone = '';

    // ----- Plan change modal -----
    public bool $showPlanModal = false;

    public ?string $planChangeUserId = null;

    public ?string $newPlanId = null;

    public function plans()
    {
        return BillingPlan::where('is_active', true)->orderBy('name')->get();
    }

    public function routers()
    {
        return Router::where('is_online', true)->get();
    }

    public function wifiUsers()
    {
        return WifiUser::with(['subscriptions' => fn ($q) => $q->with('plan')->latest()->limit(1)])
            ->when($this->search, fn ($q) => $q->where('mac_address', 'like', '%'.$this->search.'%')
                ->orWhere('phone_number', 'like', '%'.$this->search.'%'))
            ->when($this->filterStatus === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->filterStatus === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(20);
    }

    public function hotspotStats(): array
    {
        return [
            'total' => WifiUser::count(),
            'active' => WifiUser::where('is_active', true)->count(),
            'active_subs' => Subscription::where('status', 'active')->count(),
            'expired_today' => Subscription::where('status', 'expired')
                ->whereDate('expires_at', today())
                ->count(),
        ];
    }

    public function kickUser(string $userId): void
    {
        $user = WifiUser::findOrFail($userId);
        $sub = $user->subscriptions()->where('status', 'active')->with('router')->first();

        if ($sub && $sub->router) {
            $mikrotik = app(MikrotikApiService::class);
            try {
                $mikrotik->connect($sub->router);
                $mikrotik->removeHotspotUser($user->mac_address);
                $mikrotik->disconnect();
            } catch (\Exception) {
            }
        }

        $user->subscriptions()->where('status', 'active')->update(['status' => 'expired']);
        $user->update(['is_active' => false]);

        session()->flash('status', "User {$user->mac_address} kicked and deactivated.");
    }

    public function openBindModal(?string $userId = null): void
    {
        $this->reset(['bindMac', 'bindPhone']);
        $this->bindingUserId = $userId;

        if ($userId) {
            $user = WifiUser::findOrFail($userId);
            $this->bindMac = $user->mac_address;
            $this->bindPhone = $user->phone_number;
        }

        $this->showBindModal = true;
    }

    public function saveBind(): void
    {
        $this->validate(['bindMac' => 'required|regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/', 'bindPhone' => 'required|string|max:20']);

        $mac = strtoupper($this->bindMac);

        if ($this->bindingUserId) {
            WifiUser::findOrFail($this->bindingUserId)->update([
                'mac_address' => $mac,
                'phone_number' => $this->bindPhone,
            ]);
            session()->flash('status', 'User binding updated.');
        } else {
            WifiUser::create(['mac_address' => $mac, 'phone_number' => $this->bindPhone, 'is_active' => false]);
            session()->flash('status', 'MAC binding created.');
        }

        $this->showBindModal = false;
    }

    public function deleteUser(string $userId): void
    {
        $user = WifiUser::findOrFail($userId);

        $sub = $user->subscriptions()->where('status', 'active')->with('router')->first();
        if ($sub && $sub->router) {
            $mikrotik = app(MikrotikApiService::class);
            try {
                $mikrotik->connect($sub->router);
                $mikrotik->removeHotspotUser($user->mac_address);
                $mikrotik->disconnect();
            } catch (\Exception) {
            }
        }

        $user->delete();
        session()->flash('status', 'User deleted.');
    }

    public function syncProfilesNow(): void
    {
        $plans = BillingPlan::where('is_active', true)->get();
        $routers = Router::where('is_online', true)->get();
        $mikrotik = app(MikrotikApiService::class);
        $synced = 0;

        foreach ($routers as $router) {
            try {
                $mikrotik->connect($router);
                foreach ($plans as $plan) {
                    try {
                        $mikrotik->syncHotspotProfile($plan->name, $plan->upload_limit, $plan->download_limit);
                        $synced++;
                    } catch (\Exception) {
                    }
                }
                $mikrotik->disconnect();
            } catch (\Exception) {
            }
        }

        session()->flash('status', "Synced {$synced} profiles across ".count($routers)." router(s).");
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Advanced Hotspot System</h1>
        <div class="flex gap-2">
            <flux:button wire:click="syncProfilesNow" icon="arrow-path" size="sm"
                wire:loading.attr="disabled" wire:target="syncProfilesNow">
                <span wire:loading.remove wire:target="syncProfilesNow">Sync Bandwidth Profiles</span>
                <span wire:loading wire:target="syncProfilesNow">Syncing...</span>
            </flux:button>
            <flux:button wire:click="openBindModal" icon="plus" variant="primary" size="sm">Add MAC Binding</flux:button>
        </div>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    {{-- Stats --}}
    @php $stats = $this->hotspotStats(); @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <flux:card class="text-center">
            <div class="text-2xl font-bold text-zinc-700">{{ $stats['total'] }}</div>
            <flux:text class="text-sm text-zinc-500">Total Users</flux:text>
        </flux:card>
        <flux:card class="text-center">
            <div class="text-2xl font-bold text-green-600">{{ $stats['active'] }}</div>
            <flux:text class="text-sm text-zinc-500">Active Users</flux:text>
        </flux:card>
        <flux:card class="text-center">
            <div class="text-2xl font-bold text-purple-700">{{ $stats['active_subs'] }}</div>
            <flux:text class="text-sm text-zinc-500">Active Subscriptions</flux:text>
        </flux:card>
        <flux:card class="text-center">
            <div class="text-2xl font-bold text-amber-500">{{ $stats['expired_today'] }}</div>
            <flux:text class="text-sm text-zinc-500">Expired Today</flux:text>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <flux:input wire:model.live="search" placeholder="Search MAC or phone..." icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="filterStatus" class="max-w-xs">
            <flux:select.option value="all">All Users</flux:select.option>
            <flux:select.option value="active">Active Only</flux:select.option>
            <flux:select.option value="inactive">Inactive Only</flux:select.option>
        </flux:select>
    </div>

    <flux:card class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>MAC Address</flux:table.column>
                <flux:table.column>Phone</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Current Plan</flux:table.column>
                <flux:table.column>Expires</flux:table.column>
                <flux:table.column>Data Used</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->wifiUsers() as $user)
                    @php $sub = $user->subscriptions->first(); @endphp
                    <flux:table.row :key="$user->id">
                        <flux:table.cell class="font-mono font-semibold text-sm">{{ $user->mac_address }}</flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $user->phone_number ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($user->is_active)
                                <flux:badge color="green" size="sm" icon="check-circle">Active</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $sub?->plan?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $sub?->expires_at?->diffForHumans() ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $sub ? ($sub->data_used_mb.' MB') : '—' }}
                            @if ($sub?->plan?->data_quota_mb)
                                / {{ $sub->plan->data_quota_mb }} MB
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1.5">
                                <flux:button size="sm" icon="pencil"
                                    wire:click="openBindModal('{{ $user->id }}')">Edit</flux:button>
                                @if ($user->is_active)
                                    <flux:button size="sm" icon="x-mark" variant="danger"
                                        wire:click="kickUser('{{ $user->id }}')"
                                        wire:confirm="Kick and deactivate this user?">Kick</flux:button>
                                @endif
                                <flux:button size="sm" icon="trash" variant="danger"
                                    wire:click="deleteUser('{{ $user->id }}')"
                                    wire:confirm="Delete this user and all their data?">Delete</flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">{{ $this->wifiUsers()->links() }}</div>
    </flux:card>

    {{-- MAC Binding Modal --}}
    <flux:modal wire:model="showBindModal" class="w-full max-w-md">
        <div class="space-y-6">
            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">{{ $bindingUserId ? 'Edit User Binding' : 'Add MAC Binding' }}</h2>
            <div class="flex flex-col gap-4">
                <flux:input wire:model="bindMac" label="MAC Address" placeholder="AA:BB:CC:DD:EE:FF" />
                <flux:input wire:model="bindPhone" label="Phone Number" placeholder="0712345678" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showBindModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="saveBind" variant="primary">Save</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
