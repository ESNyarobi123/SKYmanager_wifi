<?php

use App\Models\BillingPlan;
use App\Models\Voucher;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public bool $showGenerateModal = false;

    public string $filterStatus = 'all';

    public string $filterBatch = '';

    public string $search = '';

    #[Validate('required|string|max:50')]
    public string $batchName = '';

    #[Validate('required|exists:billing_plans,id')]
    public string $planId = '';

    #[Validate('required|integer|min:1|max:500')]
    public int $count = 10;

    #[Validate('nullable|string|max:10')]
    public string $prefix = '';

    #[Validate('nullable|date|after:today')]
    public string $expiresAt = '';

    public function plans()
    {
        return BillingPlan::where('is_active', true)->orderBy('name')->get();
    }

    public function vouchers()
    {
        $query = Voucher::with('plan')->latest();

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterBatch !== '') {
            $query->where('batch_name', $this->filterBatch);
        }

        if ($this->search !== '') {
            $query->where('code', 'like', '%'.$this->search.'%');
        }

        return $query->paginate(20);
    }

    public function batches()
    {
        return Voucher::select('batch_name')
            ->distinct()
            ->whereNotNull('batch_name')
            ->orderBy('batch_name')
            ->pluck('batch_name');
    }

    public function stats(): array
    {
        return [
            'unused' => Voucher::where('status', 'unused')->count(),
            'used' => Voucher::where('status', 'used')->count(),
            'expired' => Voucher::where('status', 'expired')->count(),
        ];
    }

    public function generate(): void
    {
        $this->validate();

        Voucher::generateBatch(
            planId: $this->planId,
            count: $this->count,
            batchName: $this->batchName,
            prefix: $this->prefix ?: null,
            expiresAt: $this->expiresAt ? \Carbon\Carbon::parse($this->expiresAt)->endOfDay() : null,
        );

        $this->reset(['batchName', 'planId', 'count', 'prefix', 'expiresAt']);
        $this->showGenerateModal = false;
        session()->flash('status', 'Vouchers generated successfully.');
    }

    public function deleteBatch(string $batch): void
    {
        Voucher::where('batch_name', $batch)->where('status', 'unused')->delete();
        session()->flash('status', "Unused vouchers in batch '{$batch}' deleted.");
    }

    public function expireVoucher(string $id): void
    {
        Voucher::findOrFail($id)->update(['status' => 'expired']);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Vouchers</h1>
        <flux:button wire:click="$set('showGenerateModal', true)" icon="plus" variant="primary">Generate Vouchers</flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    {{-- Stats --}}
    @php $stats = $this->stats(); @endphp
    <div class="grid grid-cols-3 gap-4 mb-6">
        <flux:card class="text-center">
            <div class="text-2xl font-bold text-green-600">{{ $stats['unused'] }}</div>
            <flux:text class="text-sm text-zinc-500">Unused</flux:text>
        </flux:card>
        <flux:card class="text-center">
            <div class="text-2xl font-bold text-purple-700">{{ $stats['used'] }}</div>
            <flux:text class="text-sm text-zinc-500">Used</flux:text>
        </flux:card>
        <flux:card class="text-center">
            <div class="text-2xl font-bold text-zinc-400">{{ $stats['expired'] }}</div>
            <flux:text class="text-sm text-zinc-500">Expired</flux:text>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-3 mb-4">
        <flux:input wire:model.live="search" placeholder="Search code..." icon="magnifying-glass" class="max-w-xs" />
        <flux:select wire:model.live="filterStatus" class="max-w-xs">
            <flux:select.option value="all">All Status</flux:select.option>
            <flux:select.option value="unused">Unused</flux:select.option>
            <flux:select.option value="used">Used</flux:select.option>
            <flux:select.option value="expired">Expired</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="filterBatch" class="max-w-xs">
            <flux:select.option value="">All Batches</flux:select.option>
            @foreach ($this->batches() as $batch)
                <flux:select.option :value="$batch">{{ $batch }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Batch quick-actions --}}
    @if ($filterBatch !== '')
        <div class="mb-4 flex items-center gap-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
            <flux:text class="text-sm">Batch: <strong>{{ $filterBatch }}</strong></flux:text>
            <flux:button size="sm" variant="danger" icon="trash"
                wire:click="deleteBatch('{{ $filterBatch }}')"
                wire:confirm="Delete all UNUSED vouchers in this batch?">Delete Unused</flux:button>
        </div>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Code</flux:table.column>
            <flux:table.column>Plan</flux:table.column>
            <flux:table.column>Batch</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Used By</flux:table.column>
            <flux:table.column>Expires</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->vouchers() as $voucher)
                <flux:table.row :key="$voucher->id">
                    <flux:table.cell class="font-mono font-semibold tracking-widest text-sm">{{ $voucher->code }}</flux:table.cell>
                    <flux:table.cell>{{ $voucher->plan?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="text-sm text-zinc-500">{{ $voucher->batch_name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($voucher->status === 'unused')
                            <flux:badge color="green" size="sm">Unused</flux:badge>
                        @elseif ($voucher->status === 'used')
                            <flux:badge color="purple" size="sm">Used</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Expired</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="font-mono text-xs text-zinc-500">
                        {{ $voucher->used_by_mac ?? '—' }}
                        @if ($voucher->used_at)
                            <div class="text-zinc-400">{{ $voucher->used_at->diffForHumans() }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-sm text-zinc-500">
                        {{ $voucher->expires_at?->format('d M Y') ?? 'Never' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($voucher->status === 'unused')
                            <flux:button size="sm" variant="danger" icon="x-mark"
                                wire:click="expireVoucher('{{ $voucher->id }}')"
                                wire:confirm="Mark this voucher as expired?">Expire</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->vouchers()->links() }}
    </div>

    {{-- Generate Modal --}}
    <flux:modal wire:model="showGenerateModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">Generate Vouchers</h2>
            <div class="flex flex-col gap-4">
                <flux:input wire:model="batchName" label="Batch Name" placeholder="e.g. April-2026" />
                <flux:select wire:model="planId" label="Billing Plan">
                    <flux:select.option value="">Select plan...</flux:select.option>
                    @foreach ($this->plans() as $plan)
                        <flux:select.option :value="$plan->id">
                            {{ $plan->name }} — TZS {{ number_format($plan->price, 0) }}
                            ({{ $plan->duration_minutes >= 60 ? round($plan->duration_minutes / 60, 1).'h' : $plan->duration_minutes.'m' }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="count" label="Quantity" type="number" min="1" max="500" />
                    <flux:input wire:model="prefix" label="Prefix (optional)" placeholder="SKY" />
                </div>
                <flux:input wire:model="expiresAt" label="Expires On (optional)" type="date" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showGenerateModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="generate" variant="primary" icon="ticket">Generate</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
