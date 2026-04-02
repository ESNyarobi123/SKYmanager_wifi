<?php

use App\Models\BillingPlan;
use App\Models\Payment;
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

    // ----- Add Customer -----
    public bool $showAddModal = false;

    #[Validate('required|regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/')]
    public string $newMac = '';

    #[Validate('nullable|string|max:20')]
    public string $newPhone = '';

    #[Validate('required|exists:billing_plans,id')]
    public string $newPlanId = '';

    #[Validate('required|exists:routers,id')]
    public string $newRouterId = '';

    // ----- Recharge -----
    public bool $showRechargeModal = false;

    public ?string $rechargeUserId = null;

    public ?string $rechargeUserMac = null;

    #[Validate('required|exists:billing_plans,id')]
    public string $rechargePlanId = '';

    #[Validate('required|exists:routers,id')]
    public string $rechargeRouterId = '';

    public bool $extendExisting = true;

    public function plans()
    {
        return BillingPlan::where('is_active', true)->orderBy('name')->get();
    }

    public function routers()
    {
        return Router::orderBy('name')->get();
    }

    public function customers()
    {
        return WifiUser::with([
            'subscriptions' => fn ($q) => $q->with('plan', 'router')->latest()->limit(3),
        ])
            ->when($this->search, fn ($q) => $q
                ->where('mac_address', 'like', '%'.$this->search.'%')
                ->orWhere('phone_number', 'like', '%'.$this->search.'%'))
            ->latest()
            ->paginate(15);
    }

    public function addCustomer(): void
    {
        $this->validate([
            'newMac' => 'required|regex:/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/',
            'newPhone' => 'nullable|string|max:20',
            'newPlanId' => 'required|exists:billing_plans,id',
            'newRouterId' => 'required|exists:routers,id',
        ]);

        $mac = strtoupper($this->newMac);
        $plan = BillingPlan::findOrFail($this->newPlanId);
        $router = Router::findOrFail($this->newRouterId);

        $user = WifiUser::firstOrCreate(
            ['mac_address' => $mac],
            ['phone_number' => $this->newPhone ?? '', 'is_active' => false]
        );

        if ($this->newPhone) {
            $user->update(['phone_number' => $this->newPhone]);
        }

        $subscription = Subscription::create([
            'wifi_user_id' => $user->id,
            'plan_id' => $plan->id,
            'router_id' => $router->id,
            'expires_at' => now()->addMinutes($plan->duration_minutes),
            'status' => 'active',
        ]);

        $this->provisionToMikrotik($user, $plan, $router);
        $user->update(['is_active' => true]);

        $this->reset(['newMac', 'newPhone', 'newPlanId', 'newRouterId']);
        $this->showAddModal = false;
        session()->flash('status', "Customer {$mac} added with plan {$plan->name}. Expires: {$subscription->expires_at->format('d M Y H:i')}");
    }

    public function openRechargeModal(string $userId): void
    {
        $user = WifiUser::findOrFail($userId);
        $this->rechargeUserId = $userId;
        $this->rechargeUserMac = $user->mac_address;
        $this->rechargePlanId = '';
        $this->extendExisting = true;

        $activeSub = $user->subscriptions()->where('status', 'active')->with('router')->first();
        if ($activeSub) {
            $this->rechargePlanId = $activeSub->plan_id;
            $this->rechargeRouterId = $activeSub->router_id;
        } else {
            $this->rechargeRouterId = Router::where('is_online', true)->value('id') ?? '';
        }

        $this->showRechargeModal = true;
    }

    public function recharge(): void
    {
        $this->validate([
            'rechargePlanId' => 'required|exists:billing_plans,id',
            'rechargeRouterId' => 'required|exists:routers,id',
        ]);

        $user = WifiUser::findOrFail($this->rechargeUserId);
        $plan = BillingPlan::findOrFail($this->rechargePlanId);
        $router = Router::findOrFail($this->rechargeRouterId);

        if ($this->extendExisting) {
            $activeSub = $user->subscriptions()->where('status', 'active')->first();

            if ($activeSub) {
                $newExpiry = $activeSub->expires_at->isFuture()
                    ? $activeSub->expires_at->addMinutes($plan->duration_minutes)
                    : now()->addMinutes($plan->duration_minutes);

                $activeSub->update(['expires_at' => $newExpiry, 'plan_id' => $plan->id]);

                $this->showRechargeModal = false;
                session()->flash('status', "Recharged {$user->mac_address} — new expiry: {$newExpiry->format('d M Y H:i')}");

                return;
            }
        }

        $subscription = Subscription::create([
            'wifi_user_id' => $user->id,
            'plan_id' => $plan->id,
            'router_id' => $router->id,
            'expires_at' => now()->addMinutes($plan->duration_minutes),
            'status' => 'active',
        ]);

        $this->provisionToMikrotik($user, $plan, $router);
        $user->update(['is_active' => true]);

        $this->showRechargeModal = false;
        session()->flash('status', "New subscription created for {$user->mac_address}. Expires: {$subscription->expires_at->format('d M Y H:i')}");
    }

    private function provisionToMikrotik(WifiUser $user, BillingPlan $plan, Router $router): void
    {
        $mikrotik = app(MikrotikApiService::class);
        try {
            $mikrotik->connect($router);
            $mikrotik->removeHotspotUser($user->mac_address);
            $mikrotik->addHotspotUser(
                $user->mac_address,
                substr(md5($user->mac_address), 0, 8),
                $plan->name,
                $user->mac_address
            );
            $mikrotik->disconnect();
        } catch (\Exception) {
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Customers</h1>
        <flux:button wire:click="$set('showAddModal', true)" icon="plus" variant="primary">Add Customer</flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    <flux:input wire:model.live="search" placeholder="Search MAC or phone..." icon="magnifying-glass" class="max-w-xs mb-4" />

    <flux:card class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>MAC Address</flux:table.column>
                <flux:table.column>Phone</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Active Plan</flux:table.column>
                <flux:table.column>Expires</flux:table.column>
                <flux:table.column>Total Sessions</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->customers() as $user)
                    @php $activeSub = $user->subscriptions->firstWhere('status', 'active'); @endphp
                    <flux:table.row :key="$user->id">
                        <flux:table.cell class="font-mono font-semibold text-sm">{{ $user->mac_address }}</flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $user->phone_number ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($user->is_active)
                                <flux:badge color="green" size="sm">Active</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Inactive</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $activeSub?->plan?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            @if ($activeSub?->expires_at)
                                <span class="{{ $activeSub->expires_at->isPast() ? 'text-red-500' : '' }}">
                                    {{ $activeSub->expires_at->diffForHumans() }}
                                </span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="zinc" size="sm">{{ $user->subscriptions->count() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" icon="arrow-path" variant="primary"
                                wire:click="openRechargeModal('{{ $user->id }}')">Recharge</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-400 py-8">No customers found.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">{{ $this->customers()->links() }}</div>
    </flux:card>

    {{-- Add Customer Modal --}}
    <flux:modal wire:model="showAddModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">Add Customer</h2>
            <div class="flex flex-col gap-4">
                <flux:input wire:model="newMac" label="MAC Address" placeholder="AA:BB:CC:DD:EE:FF" />
                <flux:input wire:model="newPhone" label="Phone Number (optional)" placeholder="0712345678" />
                <flux:select wire:model="newPlanId" label="Billing Plan">
                    <flux:select.option value="">Select plan...</flux:select.option>
                    @foreach ($this->plans() as $plan)
                        <flux:select.option :value="$plan->id">
                            {{ $plan->name }} — TZS {{ number_format($plan->price, 0) }}
                            ({{ $plan->duration_minutes >= 60 ? round($plan->duration_minutes/60,1).'h' : $plan->duration_minutes.'m' }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="newRouterId" label="Router">
                    <flux:select.option value="">Select router...</flux:select.option>
                    @foreach ($this->routers() as $router)
                        <flux:select.option :value="$router->id">
                            {{ $router->name }} — {{ $router->ip_address }}
                            @if(!$router->is_online) (offline) @endif
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showAddModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="addCustomer" variant="primary" icon="plus">Add & Activate</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Recharge Modal --}}
    <flux:modal wire:model="showRechargeModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">Recharge Customer</h2>
                @if ($rechargeUserMac)
                    <p class="font-mono text-sm text-gray-500 dark:text-neutral-400">{{ $rechargeUserMac }}</p>
                @endif
            </div>
            <div class="flex flex-col gap-4">
                <flux:select wire:model="rechargePlanId" label="Plan">
                    <flux:select.option value="">Select plan...</flux:select.option>
                    @foreach ($this->plans() as $plan)
                        <flux:select.option :value="$plan->id">
                            {{ $plan->name }} — TZS {{ number_format($plan->price, 0) }}
                            ({{ $plan->duration_minutes >= 60 ? round($plan->duration_minutes/60,1).'h' : $plan->duration_minutes.'m' }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="rechargeRouterId" label="Router">
                    <flux:select.option value="">Select router...</flux:select.option>
                    @foreach ($this->routers() as $router)
                        <flux:select.option :value="$router->id">{{ $router->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:checkbox wire:model="extendExisting" label="Extend existing active subscription (if any)" />
            </div>
            <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-sm text-blue-700 dark:text-blue-300">
                <strong>Extend ON:</strong> adds time on top of the current expiry.<br>
                <strong>Extend OFF:</strong> creates a fresh new subscription.
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showRechargeModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="recharge" variant="primary" icon="arrow-path">Recharge</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
