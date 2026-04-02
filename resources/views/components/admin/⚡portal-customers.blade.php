<?php

use App\Models\ActivityLog;
use App\Models\CustomerPaymentGateway;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public ?string $viewingId = null;

    public bool $showDetailModal = false;

    public bool $showNotifyModal = false;

    public ?string $notifyTargetId = null;

    #[Validate('required|string|max:200')]
    public string $notifyTitle = '';

    #[Validate('required|string|max:1000')]
    public string $notifyMessage = '';

    public string $notifyType = 'info';

    public function customers()
    {
        return User::withTrashed()
            ->whereHas('roles', fn ($q) => $q->where('name', 'customer'))
            ->withCount(['routers', 'invoices', 'referrals'])
            ->with('paymentGateways')
            ->when($this->search, fn ($q) => $q
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('phone', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')
                ->orWhere('company_name', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter === 'active', fn ($q) => $q->whereNull('deleted_at')->where('is_suspended', false))
            ->when($this->statusFilter === 'suspended', fn ($q) => $q->where('is_suspended', true))
            ->when($this->statusFilter === 'deleted', fn ($q) => $q->onlyTrashed())
            ->latest()
            ->paginate(15);
    }

    public function viewingCustomer(): ?User
    {
        if (! $this->viewingId) {
            return null;
        }

        return User::withTrashed()
            ->with([
                'routers.subscriptions.plan',
                'invoices' => fn ($q) => $q->latest()->take(5),
                'referrals.referred',
                'paymentGateways',
            ])
            ->find($this->viewingId);
    }

    public function openDetail(string $id): void
    {
        $this->viewingId = $id;
        $this->showDetailModal = true;
    }

    public function suspend(string $id): void
    {
        $customer = User::findOrFail($id);
        $customer->update(['is_suspended' => ! $customer->is_suspended]);

        $action = $customer->is_suspended ? 'suspended' : 'unsuspended';
        ActivityLog::record("Admin {$action} customer account", $customer, auth()->user());

        session()->flash('status', "Customer {$customer->name} has been {$action}.");
    }

    public function delete(string $id): void
    {
        $customer = User::findOrFail($id);
        ActivityLog::record('Admin soft-deleted customer account', $customer, auth()->user());
        $customer->delete();

        session()->flash('status', "Customer {$customer->name} has been deleted.");
        $this->showDetailModal = false;
    }

    public function restore(string $id): void
    {
        $customer = User::withTrashed()->findOrFail($id);
        $customer->restore();
        ActivityLog::record('Admin restored customer account', $customer, auth()->user());

        session()->flash('status', "Customer {$customer->name} has been restored.");
    }

    public function openNotifyModal(string $id): void
    {
        $this->notifyTargetId = $id;
        $this->notifyTitle = '';
        $this->notifyMessage = '';
        $this->notifyType = 'info';
        $this->showNotifyModal = true;
    }

    public function sendNotification(): void
    {
        $this->validate([
            'notifyTitle' => 'required|string|max:200',
            'notifyMessage' => 'required|string|max:1000',
        ]);

        $customer = User::findOrFail($this->notifyTargetId);

        $customer->notify(new \App\Notifications\AdminDirectMessage(
            $this->notifyTitle,
            $this->notifyMessage,
            $this->notifyType
        ));

        ActivityLog::record(
            "Admin sent notification to customer: {$this->notifyTitle}",
            $customer,
            auth()->user(),
            ['type' => $this->notifyType]
        );

        $this->showNotifyModal = false;
        $this->reset(['notifyTitle', 'notifyMessage', 'notifyType']);
        session()->flash('status', "Notification sent to {$customer->name}.");
    }

    public function disableGateway(string $gatewayId): void
    {
        $gateway = CustomerPaymentGateway::findOrFail($gatewayId);
        $gateway->update(['is_active' => false, 'verified_at' => null]);

        ActivityLog::record(
            'Admin force-disabled customer ClickPesa gateway',
            $gateway->customer,
            auth()->user()
        );

        session()->flash('status', 'ClickPesa gateway has been disabled for this customer.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    {{-- ── Header ────────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('Portal Accounts') }}</h1>
            <p class="text-sm text-gray-500 dark:text-neutral-500 mt-1">{{ __('Manage customer self-service portal accounts') }}</p>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-neutral-400">
            <x-lucide name="users" class="size-4"/>
            <span>{{ \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'customer'))->count() }} {{ __('total accounts') }}</span>
        </div>
    </div>

    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 dark:bg-emerald-800/10 dark:border-emerald-900 dark:text-emerald-500 mb-4" role="alert"><div class="flex gap-x-3"><x-lucide name="check-circle" class="size-4 shrink-0 mt-0.5"/><p class="text-sm">{{ session('status') }}</p></div></div>
    @endif

    {{-- ── Filters ────────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <flux:input wire:model.live="search" placeholder="{{ __('Search name, phone, email…') }}" icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="statusFilter" class="w-36">
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="suspended">{{ __('Suspended') }}</flux:select.option>
            <flux:select.option value="deleted">{{ __('Deleted') }}</flux:select.option>
        </flux:select>
    </div>

    {{-- ── Table ─────────────────────────────────────────────────────────────── --}}
    <flux:card class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Routers') }}</flux:table.column>
                <flux:table.column>{{ __('Revenue') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('ClickPesa') }}</flux:table.column>
                <flux:table.column>{{ __('Joined') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->customers() as $customer)
                    <flux:table.row :key="$customer->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/40 text-xs font-bold text-purple-600 dark:text-purple-400">
                                    {{ $customer->initials() }}
                                </div>
                                <div>
                                    <p class="font-medium text-sm text-zinc-900 dark:text-white">{{ $customer->name }}</p>
                                    @if($customer->company_name)
                                        <p class="text-xs text-zinc-400">{{ $customer->company_name }}</p>
                                    @endif
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">{{ $customer->phone }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge color="zinc" size="sm">{{ $customer->routers_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="font-semibold text-sm">
                            TZS {{ number_format($customer->totalRevenue()) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($customer->trashed())
                                <flux:badge color="red" size="sm">{{ __('Deleted') }}</flux:badge>
                            @elseif($customer->is_suspended)
                                <flux:badge color="orange" size="sm">{{ __('Suspended') }}</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @php $cpGateway = $customer->paymentGateways->firstWhere('gateway', 'clickpesa'); @endphp
                            @if($cpGateway?->isConfigured())
                                <flux:badge color="{{ $cpGateway->isVerified() ? 'green' : 'amber' }}" size="sm"
                                    title="{{ $cpGateway->isVerified() ? __('Verified') : __('Saved but not verified') }}">
                                    {{ $cpGateway->isVerified() ? __('Connected') : __('Unverified') }}
                                </flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Not set') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-400">
                            {{ $customer->created_at->format('d M Y') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-1">
                                <flux:button size="sm" variant="ghost" icon="eye"
                                    wire:click="openDetail('{{ $customer->id }}')"
                                    title="{{ __('View details') }}"
                                />
                                @if(!$customer->trashed())
                                    <flux:button size="sm" variant="ghost" icon="bell"
                                        wire:click="openNotifyModal('{{ $customer->id }}')"
                                        title="{{ __('Send notification') }}"
                                    />
                                    <flux:button size="sm" variant="ghost"
                                        icon="{{ $customer->is_suspended ? 'lock-open' : 'lock-closed' }}"
                                        wire:click="suspend('{{ $customer->id }}')"
                                        wire:confirm="{{ $customer->is_suspended ? __('Unsuspend this customer?') : __('Suspend this customer? They will lose portal access.') }}"
                                        title="{{ $customer->is_suspended ? __('Unsuspend') : __('Suspend') }}"
                                    />
                                    <flux:button size="sm" variant="ghost" icon="trash"
                                        wire:click="delete('{{ $customer->id }}')"
                                        wire:confirm="{{ __('Soft-delete this customer? They can be restored later.') }}"
                                        title="{{ __('Delete') }}"
                                        class="text-red-500 hover:text-red-600"
                                    />
                                @else
                                    <flux:button size="sm" variant="ghost" icon="arrow-path"
                                        wire:click="restore('{{ $customer->id }}')"
                                        title="{{ __('Restore') }}"
                                        class="text-green-600"
                                    />
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-400 py-8">
                            {{ __('No portal customers found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">{{ $this->customers()->links() }}</div>
    </flux:card>

    {{-- ── Detail Modal ─────────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showDetailModal" name="customer-detail" class="w-full max-w-2xl">
        @if($detail = $this->viewingCustomer())
            <div class="space-y-5">
                {{-- Header --}}
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/40 text-lg font-bold text-purple-600 dark:text-purple-400">
                            {{ $detail->initials() }}
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">{{ $detail->name }}</h2>
                            @if($detail->company_name)
                                <p class="text-sm text-gray-500 dark:text-neutral-400">{{ $detail->company_name }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex gap-1">
                        @if($detail->is_suspended)
                            <flux:badge color="orange">{{ __('Suspended') }}</flux:badge>
                        @else
                            <flux:badge color="green">{{ __('Active') }}</flux:badge>
                        @endif
                    </div>
                </div>

                <flux:separator />

                {{-- Info Grid --}}
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">{{ __('Phone') }}</p>
                        <p class="mt-1 font-mono text-zinc-800 dark:text-zinc-200">{{ $detail->phone }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">{{ __('Email') }}</p>
                        <p class="mt-1 text-zinc-800 dark:text-zinc-200">{{ $detail->email ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">{{ __('Referral Code') }}</p>
                        <p class="mt-1 font-mono text-sky-600 dark:text-sky-400">{{ $detail->referral_code ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">{{ __('Total Revenue') }}</p>
                        <p class="mt-1 font-semibold text-zinc-800 dark:text-zinc-200">TZS {{ number_format($detail->totalRevenue()) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">{{ __('Referrals Made') }}</p>
                        <p class="mt-1 text-zinc-800 dark:text-zinc-200">{{ $detail->referrals->count() }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">{{ __('Joined') }}</p>
                        <p class="mt-1 text-zinc-800 dark:text-zinc-200">{{ $detail->created_at->format('d M Y') }}</p>
                    </div>
                </div>

                {{-- Routers --}}
                @if($detail->routers->isNotEmpty())
                    <flux:separator />
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider mb-2">{{ __('Routers') }} ({{ $detail->routers->count() }})</p>
                        <div class="space-y-2">
                            @foreach($detail->routers->take(5) as $router)
                                <div class="flex items-center justify-between rounded-lg bg-zinc-50 dark:bg-zinc-800 px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2 w-2 rounded-full {{ $router->is_online ? 'bg-green-500' : 'bg-zinc-400' }}"></span>
                                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $router->name }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-xs font-mono text-zinc-400">{{ $router->ip_address }}</p>
                                        @php $activeSub = $router->subscriptions->firstWhere('status', 'active'); @endphp
                                        @if($activeSub)
                                            <flux:badge color="green" size="sm">{{ $activeSub->plan->name ?? '—' }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">{{ __('No plan') }}</flux:badge>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Recent Invoices --}}
                @if($detail->invoices->isNotEmpty())
                    <flux:separator />
                    <div>
                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider mb-2">{{ __('Recent Invoices') }}</p>
                        <div class="space-y-1">
                            @foreach($detail->invoices->take(5) as $invoice)
                                <div class="flex items-center justify-between text-sm px-3 py-1.5 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                                    <span class="font-mono text-sky-600 dark:text-sky-400 text-xs">{{ $invoice->invoice_number }}</span>
                                    <span class="text-zinc-600 dark:text-zinc-400">TZS {{ number_format((float) $invoice->total) }}</span>
                                    <flux:badge color="{{ $invoice->status === 'paid' ? 'green' : 'zinc' }}" size="sm">
                                        {{ ucfirst($invoice->status) }}
                                    </flux:badge>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ClickPesa Gateway Status --}}
                @php $cpGateway = $detail->paymentGateways->firstWhere('gateway', 'clickpesa'); @endphp
                <flux:separator />
                <div>
                    <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider mb-2">{{ __('ClickPesa Gateway') }}</p>
                    @if($cpGateway)
                        <div class="flex items-center justify-between rounded-lg px-3 py-2.5
                            {{ $cpGateway->isConfigured() ? 'bg-green-50 dark:bg-green-900/20' : 'bg-zinc-50 dark:bg-zinc-800' }}">
                            <div class="flex items-center gap-2.5">
                                <x-lucide name="{{ $cpGateway->isConfigured() ? 'check-circle' : 'x-circle' }}"
                                    class="size-4 {{ $cpGateway->isConfigured() ? 'text-green-500' : 'text-gray-400' }}"/>
                                <div>
                                    <p class="text-sm font-medium {{ $cpGateway->isConfigured() ? 'text-green-800 dark:text-green-300' : 'text-zinc-500' }}">
                                        {{ $cpGateway->isConfigured() ? ($cpGateway->isVerified() ? __('Connected & Verified') : __('Saved — Not Verified')) : __('Disabled') }}
                                    </p>
                                    @if($cpGateway->last_used_at)
                                        <p class="text-xs text-zinc-400">{{ __('Last used') }}: {{ $cpGateway->last_used_at->diffForHumans() }}</p>
                                    @endif
                                </div>
                            </div>
                            @if($cpGateway->isConfigured())
                                <flux:button size="sm" variant="ghost"
                                    wire:click="disableGateway('{{ $cpGateway->id }}')"
                                    wire:confirm="{{ __('Force-disable this customer\'s ClickPesa gateway?') }}"
                                    class="text-red-500 hover:text-red-600 text-xs">
                                    {{ __('Force Disable') }}
                                </flux:button>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-zinc-400 px-3">{{ __('No ClickPesa credentials configured.') }}</p>
                    @endif
                </div>

                <div class="flex justify-between pt-2">
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="ghost" icon="bell"
                            wire:click="openNotifyModal('{{ $detail->id }}')"
                            wire:click.stop="$set('showDetailModal', false)">
                            {{ __('Send Notification') }}
                        </flux:button>
                    </div>
                    <flux:button wire:click="$set('showDetailModal', false)" variant="ghost">{{ __('Close') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- ── Send Notification Modal ─────────────────────────────────────────── --}}
    <flux:modal wire:model="showNotifyModal" name="send-notification" class="w-full max-w-md">
        <div class="space-y-4">
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">{{ __('Send Notification') }}</h2>
                <p class="text-sm text-gray-500 dark:text-neutral-400 mt-1">{{ __('Customer will see this in their Notifications page.') }}</p>
            </div>
            <flux:field>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input wire:model="notifyTitle" placeholder="{{ __('e.g. Important update') }}" />
                <flux:error name="notifyTitle" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Message') }}</flux:label>
                <flux:textarea wire:model="notifyMessage" rows="3" placeholder="{{ __('Your message…') }}" />
                <flux:error name="notifyMessage" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Type') }}</flux:label>
                <flux:select wire:model="notifyType">
                    <flux:select.option value="info">{{ __('Info') }}</flux:select.option>
                    <flux:select.option value="success">{{ __('Success') }}</flux:select.option>
                    <flux:select.option value="warning">{{ __('Warning') }}</flux:select.option>
                    <flux:select.option value="error">{{ __('Error') }}</flux:select.option>
                </flux:select>
            </flux:field>
            <div class="flex justify-end gap-2 pt-1">
                <flux:button wire:click="$set('showNotifyModal', false)" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button wire:click="sendNotification" variant="primary" icon="bell">{{ __('Send') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
