<?php

use App\Services\RadiusService;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    // ----- Auth Tester -----
    #[Validate('required|string|max:253')]
    public string $radiusServer = '';

    #[Validate('required|string|max:200')]
    public string $radiusSecret = '';

    #[Validate('required|string|max:100')]
    public string $radiusUsername = '';

    #[Validate('required|string|max:200')]
    public string $radiusPassword = '';

    public int $radiusPort = 1812;

    public ?array $authResult = null;

    public bool $authTesting = false;

    // ----- Online Users -----
    public bool $radiusDbConfigured = false;

    public ?string $radiusDbError = null;

    public function mount(): void
    {
        $this->radiusServer = config('database.connections.radius.host', '127.0.0.1');
        $this->radiusDbConfigured = ! empty(config('database.connections.radius.database'));

        try {
            if ($this->radiusDbConfigured) {
                \Illuminate\Support\Facades\DB::connection('radius')->getPdo();
            }
        } catch (\Exception $e) {
            $this->radiusDbError = $e->getMessage();
        }
    }

    public function onlineSessions()
    {
        if (! $this->radiusDbConfigured || $this->radiusDbError) {
            return collect();
        }

        try {
            return \Illuminate\Support\Facades\DB::connection('radius')
                ->table('radacct')
                ->whereNull('acctstoptime')
                ->orderByDesc('acctstarttime')
                ->limit(100)
                ->get();
        } catch (\Exception) {
            return collect();
        }
    }

    public function testAuth(): void
    {
        $this->validate([
            'radiusServer' => 'required|string|max:253',
            'radiusSecret' => 'required|string|max:200',
            'radiusUsername' => 'required|string|max:100',
            'radiusPassword' => 'required|string|max:200',
        ]);

        $this->authTesting = true;
        $this->authResult = null;

        try {
            $radius = app(RadiusService::class);
            $this->authResult = $radius->testAuth(
                $this->radiusServer,
                $this->radiusSecret,
                $this->radiusUsername,
                $this->radiusPassword,
                $this->radiusPort
            );
        } catch (\Exception $e) {
            $this->authResult = [
                'success' => false,
                'code' => 0,
                'message' => $e->getMessage(),
                'latency_ms' => 0,
            ];
        }

        $this->authTesting = false;
    }

    public function stopSession(int $radacctid): void
    {
        if (! $this->radiusDbConfigured) {
            return;
        }

        try {
            $radius = app(RadiusService::class);
            $radius->stopSession($radacctid);
            session()->flash('status', "Session #{$radacctid} stopped.");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }
};
?>

<div>
    <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200 mb-6">RADIUS Tools</h1>

    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 dark:bg-emerald-800/10 dark:border-emerald-900 dark:text-emerald-500 mb-4" role="alert"><div class="flex gap-x-3"><x-lucide name="check-circle" class="size-4 shrink-0 mt-0.5"/><p class="text-sm">{{ session('status') }}</p></div></div>
    @endif
    @if (session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 dark:bg-red-800/10 dark:border-red-900 dark:text-red-500 mb-4" role="alert"><div class="flex gap-x-3"><x-lucide name="x-circle" class="size-4 shrink-0 mt-0.5"/><p class="text-sm">{{ session('error') }}</p></div></div>
    @endif

    <div class="grid lg:grid-cols-2 gap-8 mb-8">

        {{-- RADIUS Auth Tester --}}
        <div>
            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200 mb-4">🔐 RADIUS Client Tester</h2>
            <flux:card class="space-y-4">
                <flux:input wire:model="radiusServer" label="RADIUS Server IP" placeholder="127.0.0.1" />
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="radiusPort" label="Auth Port" type="number" />
                    <flux:input wire:model="radiusSecret" label="Shared Secret" type="password" placeholder="testing123" />
                </div>
                <flux:input wire:model="radiusUsername" label="Username" placeholder="testuser" />
                <flux:input wire:model="radiusPassword" label="Password" type="password" placeholder="••••••••" />

                <flux:button wire:click="testAuth" wire:loading.attr="disabled"
                    wire:target="testAuth" variant="primary" icon="key" class="w-full">
                    <span wire:loading.remove wire:target="testAuth">Send Access-Request</span>
                    <span wire:loading wire:target="testAuth">Sending...</span>
                </flux:button>

                @if ($authResult !== null)
                    <div class="p-4 rounded-lg border {{ $authResult['success'] ? 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-700' : 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-700' }} space-y-2">
                        <div class="flex items-center gap-2 font-semibold {{ $authResult['success'] ? 'text-green-700' : 'text-red-700' }}">
                            <span>{{ $authResult['success'] ? '✅' : '❌' }}</span>
                            <span>{{ $authResult['message'] }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs text-zinc-500">
                            <div>Response Code: <strong>{{ $authResult['code'] }}</strong></div>
                            <div>Latency: <strong>{{ $authResult['latency_ms'] }} ms</strong></div>
                        </div>
                        <div class="text-xs text-zinc-400">
                            Code 2 = Access-Accept &bull; Code 3 = Access-Reject
                        </div>
                    </div>
                @endif
            </flux:card>
        </div>

        {{-- RADIUS Config Status --}}
        <div>
            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200 mb-4">⚙️ RADIUS Database Config</h2>
            <flux:card class="space-y-3">
                @if ($radiusDbConfigured && ! $radiusDbError)
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-3 dark:bg-emerald-800/10 dark:border-emerald-900 dark:text-emerald-500 text-sm">RADIUS database connection is active. Online users are shown below.</div>
                @elseif ($radiusDbError)
                    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-3 dark:bg-red-800/10 dark:border-red-900 dark:text-red-500 text-sm"><strong>DB Error:</strong> {{ $radiusDbError }}</div>
                @else
                    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-3 dark:bg-amber-800/10 dark:border-amber-900 dark:text-amber-500 text-sm">RADIUS database not configured. Add these to your <code>.env</code>:</div>
                @endif

                <div class="rounded-lg bg-zinc-950 border border-zinc-800 p-3 text-xs font-mono text-zinc-300 leading-relaxed">
                    RADIUS_DB_HOST=127.0.0.1<br>
                    RADIUS_DB_PORT=3306<br>
                    RADIUS_DB_DATABASE=radius<br>
                    RADIUS_DB_USERNAME=radius<br>
                    RADIUS_DB_PASSWORD=your_password
                </div>

                <div class="text-xs text-zinc-400">
                    The RADIUS connection uses a separate DB (<code>radius</code> key in <code>database.php</code>).
                    Online users are queried from the <code>radacct</code> table where <code>acctstoptime IS NULL</code>.
                </div>
            </flux:card>
        </div>
    </div>

    {{-- Online Sessions --}}
    <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200 mb-3">� RADIUS Online Users</h2>

    @if (!$radiusDbConfigured || $radiusDbError)
        <flux:card>
            <div class="text-center py-8 text-zinc-400">
                Configure RADIUS database above to view online users.
            </div>
        </flux:card>
    @else
        @php $sessions = $this->onlineSessions(); @endphp
        <flux:card class="overflow-x-auto">
            @if ($sessions->isEmpty())
                <div class="text-center py-8 text-zinc-400">No active RADIUS sessions.</div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Username</flux:table.column>
                        <flux:table.column>NAS IP</flux:table.column>
                        <flux:table.column>Called Station</flux:table.column>
                        <flux:table.column>Frame IP</flux:table.column>
                        <flux:table.column>Session Start</flux:table.column>
                        <flux:table.column>Input / Output</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($sessions as $s)
                            <flux:table.row :key="$s->radacctid">
                                <flux:table.cell class="font-semibold">{{ $s->username }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-sm">{{ $s->nasipaddress }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ $s->calledstationid }}</flux:table.cell>
                                <flux:table.cell class="font-mono text-sm">{{ $s->framedipaddress }}</flux:table.cell>
                                <flux:table.cell class="text-sm">{{ \Carbon\Carbon::parse($s->acctstarttime)->diffForHumans() }}</flux:table.cell>
                                <flux:table.cell class="text-sm">
                                    ⬇ {{ round(($s->acctinputoctets ?? 0) / 1048576, 2) }} MB
                                    / ⬆ {{ round(($s->acctoutputoctets ?? 0) / 1048576, 2) }} MB
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button size="sm" variant="danger" icon="x-mark"
                                        wire:click="stopSession({{ $s->radacctid }})"
                                        wire:confirm="Stop this RADIUS session?">Stop</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif
</div>
