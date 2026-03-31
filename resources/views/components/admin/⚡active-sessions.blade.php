<?php

use App\Models\Subscription;
use App\Services\MikrotikApiService;
use Livewire\Component;

new class extends Component
{
    public function activeSessions()
    {
        return Subscription::query()
            ->where('status', 'active')
            ->with(['wifiUser', 'plan', 'router', 'latestPayment'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function kickUser(string $subscriptionId, MikrotikApiService $mikrotik): void
    {
        $subscription = Subscription::with(['wifiUser', 'router'])->findOrFail($subscriptionId);

        try {
            $mikrotik->connect($subscription->router)
                ->removeHotspotUser($subscription->wifiUser->mac_address);
            $mikrotik->disconnect();
        } catch (\Exception $e) {
            session()->flash('error', 'Could not disconnect user: '.$e->getMessage());
        }

        $subscription->update(['status' => 'expired']);
        $subscription->wifiUser->update(['is_active' => false]);
        session()->flash('status', 'User disconnected.');
    }
};
?>

<div wire:poll.10s>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">Active Sessions</flux:heading>
            <flux:text class="text-sm text-zinc-500">Auto-refreshes every 10 seconds</flux:text>
        </div>
        <flux:badge color="green" size="lg">{{ $this->activeSessions()->count() }} Online</flux:badge>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('status') }}</flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" icon="x-circle" class="mb-4">{{ session('error') }}</flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>MAC Address</flux:table.column>
            <flux:table.column>Phone</flux:table.column>
            <flux:table.column>Plan</flux:table.column>
            <flux:table.column>Router</flux:table.column>
            <flux:table.column>Expires</flux:table.column>
            <flux:table.column>Payment</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->activeSessions() as $session)
                <flux:table.row :key="$session->id">
                    <flux:table.cell class="font-mono text-sm">{{ $session->wifiUser->mac_address }}</flux:table.cell>
                    <flux:table.cell>{{ $session->wifiUser->phone_number ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <div>{{ $session->plan->name }}</div>
                        <div class="text-xs text-zinc-400">TZS {{ number_format($session->plan->price, 0) }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $session->router->name }}</flux:table.cell>
                    <flux:table.cell>
                        @php $remainingMinutes = now()->diffInMinutes($session->expires_at, false); @endphp
                        @if ($remainingMinutes > 0)
                            <flux:badge color="green" size="sm">{{ $session->expires_at->diffForHumans() }}</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">Expiring</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($session->latestPayment)
                            <flux:badge
                                :color="match($session->latestPayment->status) { 'success' => 'green', 'pending' => 'yellow', default => 'red' }"
                                size="sm">
                                {{ ucfirst($session->latestPayment->status) }}
                            </flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">No Payment</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" icon="x-mark" variant="danger"
                            wire:click="kickUser('{{ $session->id }}')"
                            wire:confirm="Disconnect this user?">Kick</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center py-8 text-zinc-400">
                        No active sessions.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>