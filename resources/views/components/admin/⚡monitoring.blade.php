<?php

use App\Models\Router;
use App\Services\MikrotikApiService;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    public ?string $selectedRouterId = null;

    public array $resources = [];

    public array $sessions = [];

    public array $interfaces = [];

    public string $error = '';

    public bool $loading = false;

    public function mount(): void
    {
        $first = Router::where('is_online', true)->first();
        if ($first) {
            $this->selectedRouterId = $first->id;
            $this->fetchStats();
        }
    }

    public function routers()
    {
        return Router::orderBy('name')->get();
    }

    public bool $fromCache = false;

    public ?string $cachedAt = null;

    private const CACHE_TTL = 30;

    public function fetchStats(bool $force = false): void
    {
        if (! $this->selectedRouterId) {
            return;
        }

        $router = Router::find($this->selectedRouterId);
        if (! $router) {
            return;
        }

        $cacheKey = 'router_stats_'.$this->selectedRouterId;

        if (! $force && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            $this->resources = $cached['resources'];
            $this->sessions = $cached['sessions'];
            $this->interfaces = $cached['interfaces'];
            $this->cachedAt = $cached['fetched_at'];
            $this->fromCache = true;
            $this->error = '';

            return;
        }

        $mikrotik = app(MikrotikApiService::class);
        $this->error = '';
        $this->fromCache = false;

        try {
            $mikrotik->connect($router);
            $this->resources = $mikrotik->getSystemResources();
            $this->sessions = $mikrotik->getActiveHotspotSessions();
            $this->interfaces = $mikrotik->getInterfaceStats();
            $mikrotik->disconnect();

            $this->cachedAt = now()->toTimeString();

            Cache::put($cacheKey, [
                'resources' => $this->resources,
                'sessions' => $this->sessions,
                'interfaces' => $this->interfaces,
                'fetched_at' => $this->cachedAt,
            ], self::CACHE_TTL);

            $router->update(['is_online' => true, 'last_seen' => now()]);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $router->update(['is_online' => false]);
        }
    }

    public function forceRefresh(): void
    {
        Cache::forget('router_stats_'.$this->selectedRouterId);
        $this->fetchStats(force: true);
    }

    public function kickSession(string $sessionId): void
    {
        if (! $this->selectedRouterId) {
            return;
        }

        $router = Router::find($this->selectedRouterId);
        if (! $router) {
            return;
        }

        $mikrotik = app(MikrotikApiService::class);

        try {
            $mikrotik->connect($router);
            $mikrotik->kickHotspotSession($sessionId);
            $mikrotik->disconnect();
            $this->fetchStats();
            session()->flash('status', 'Session disconnected.');
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
};
?>

<div wire:poll.10s="fetchStats">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Mikrotik Monitoring</h1>
            @if ($cachedAt)
                @if ($fromCache)
                    <flux:badge color="zinc" size="sm" icon="clock">Cached {{ $cachedAt }}</flux:badge>
                @else
                    <flux:badge color="green" size="sm" icon="signal">Live {{ $cachedAt }}</flux:badge>
                @endif
            @endif
        </div>
        <div class="flex items-center gap-3">
            <flux:select wire:model.live="selectedRouterId" wire:change="fetchStats" class="max-w-xs">
                @foreach ($this->routers() as $router)
                    <flux:select.option :value="$router->id">
                        {{ $router->name }} — {{ $router->ip_address }}
                        @if (!$router->is_online) (offline) @endif
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:button wire:click="forceRefresh" icon="arrow-path" size="sm"
                wire:loading.attr="disabled" wire:target="forceRefresh">
                <span wire:loading.remove wire:target="forceRefresh">Refresh</span>
                <span wire:loading wire:target="forceRefresh">...</span>
            </flux:button>
        </div>
    </div>

    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 dark:bg-emerald-800/10 dark:border-emerald-900 dark:text-emerald-500 mb-4" role="alert"><div class="flex gap-x-3"><x-lucide name="check-circle" class="size-4 shrink-0 mt-0.5"/><p class="text-sm">{{ session('status') }}</p></div></div>
    @endif

    @if ($error)
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 dark:bg-red-800/10 dark:border-red-900 dark:text-red-500 mb-4" role="alert"><div class="flex gap-x-3"><x-lucide name="activity" class="size-4 shrink-0 mt-0.5"/><p class="text-sm">{{ $error }}</p></div></div>
    @endif

    @if (!empty($resources))
        {{-- System Resource Cards --}}
        @php
            $cpu = (int) ($resources['cpu-load'] ?? 0);
            $totalMem = (int) ($resources['total-memory'] ?? 0);
            $freeMem = (int) ($resources['free-memory'] ?? 0);
            $usedMem = $totalMem - $freeMem;
            $memPct = $totalMem > 0 ? round($usedMem / $totalMem * 100) : 0;
            $totalDisk = (int) ($resources['total-hdd-space'] ?? 0);
            $freeDisk = (int) ($resources['free-hdd-space'] ?? 0);
            $diskPct = $totalDisk > 0 ? round(($totalDisk - $freeDisk) / $totalDisk * 100) : 0;
        @endphp

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <flux:card>
                <flux:text class="text-sm text-zinc-500">RouterOS</flux:text>
                <div class="font-bold text-sm mt-1">{{ $resources['version'] ?? '—' }}</div>
                <div class="text-xs text-zinc-400">{{ $resources['board-name'] ?? '' }}</div>
            </flux:card>
            <flux:card>
                <flux:text class="text-sm text-zinc-500">Uptime</flux:text>
                <div class="font-bold text-sm mt-1">{{ $resources['uptime'] ?? '—' }}</div>
                <div class="text-xs text-zinc-400">{{ $resources['platform'] ?? '' }}</div>
            </flux:card>
            <flux:card>
                <flux:text class="text-sm text-zinc-500">CPU Load</flux:text>
                <div class="font-bold text-2xl mt-1 {{ $cpu > 80 ? 'text-red-600' : ($cpu > 50 ? 'text-amber-500' : 'text-green-600') }}">{{ $cpu }}%</div>
                <div class="w-full bg-zinc-100 dark:bg-zinc-700 rounded-full h-1.5 mt-1">
                    <div class="h-1.5 rounded-full {{ $cpu > 80 ? 'bg-red-500' : ($cpu > 50 ? 'bg-amber-400' : 'bg-green-500') }}" style="width:{{ $cpu }}%"></div>
                </div>
            </flux:card>
            <flux:card>
                <flux:text class="text-sm text-zinc-500">Memory</flux:text>
                <div class="font-bold text-sm mt-1">{{ $this->formatBytes($usedMem) }} / {{ $this->formatBytes($totalMem) }}</div>
                <div class="w-full bg-zinc-100 dark:bg-zinc-700 rounded-full h-1.5 mt-1">
                    <div class="h-1.5 rounded-full {{ $memPct > 80 ? 'bg-red-500' : 'bg-purple-500' }}" style="width:{{ $memPct }}%"></div>
                </div>
                <div class="text-xs text-zinc-400">{{ $memPct }}% used</div>
            </flux:card>
        </div>

        {{-- Active Sessions --}}
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200 flex items-center gap-2">Active Hotspot Sessions
                <flux:badge color="purple" size="sm">{{ count($sessions) }}</flux:badge>
            </h2>
        </div>

        <flux:card class="mb-6 overflow-x-auto">
            @if (count($sessions) > 0)
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>User / MAC</flux:table.column>
                        <flux:table.column>IP Address</flux:table.column>
                        <flux:table.column>Uptime</flux:table.column>
                        <flux:table.column>Bytes In</flux:table.column>
                        <flux:table.column>Bytes Out</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($sessions as $session)
                            <flux:table.row :key="$session['.id'] ?? $loop->index">
                                <flux:table.cell>
                                    <div class="font-semibold">{{ $session['user'] ?? '—' }}</div>
                                    <div class="text-xs text-zinc-400 font-mono">{{ $session['mac-address'] ?? '' }}</div>
                                </flux:table.cell>
                                <flux:table.cell class="font-mono text-sm">{{ $session['address'] ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $session['uptime'] ?? '—' }}</flux:table.cell>
                                <flux:table.cell class="text-sm">{{ $this->formatBytes((int)($session['bytes-in'] ?? 0)) }}</flux:table.cell>
                                <flux:table.cell class="text-sm">{{ $this->formatBytes((int)($session['bytes-out'] ?? 0)) }}</flux:table.cell>
                                <flux:table.cell>
                                    @if (isset($session['.id']))
                                        <flux:button size="sm" variant="danger" icon="x-mark"
                                            wire:click="kickSession('{{ $session['.id'] }}')"
                                            wire:confirm="Disconnect this session?">Kick</flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <div class="text-center py-8 text-zinc-400">No active sessions</div>
            @endif
        </flux:card>

        {{-- Interfaces --}}
        <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200 mb-3">Interfaces</h2>
        <flux:card class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Interface</flux:table.column>
                    <flux:table.column>RX Bytes</flux:table.column>
                    <flux:table.column>TX Bytes</flux:table.column>
                    <flux:table.column>RX Errors</flux:table.column>
                    <flux:table.column>TX Errors</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($interfaces as $iface)
                        <flux:table.row :key="$iface['name'] ?? $loop->index">
                            <flux:table.cell class="font-mono font-semibold">{{ $iface['name'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $this->formatBytes((int)($iface['rx-byte'] ?? 0)) }}</flux:table.cell>
                            <flux:table.cell>{{ $this->formatBytes((int)($iface['tx-byte'] ?? 0)) }}</flux:table.cell>
                            <flux:table.cell class="{{ ($iface['rx-error'] ?? 0) > 0 ? 'text-red-500' : 'text-zinc-400' }}">{{ $iface['rx-error'] ?? 0 }}</flux:table.cell>
                            <flux:table.cell class="{{ ($iface['tx-error'] ?? 0) > 0 ? 'text-red-500' : 'text-zinc-400' }}">{{ $iface['tx-error'] ?? 0 }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @elseif (!$error)
        <div class="text-center py-16 text-zinc-400">
            <div class="text-4xl mb-3">📡</div>
            <p>Select a router and click Refresh to load stats.</p>
        </div>
    @endif
</div>
