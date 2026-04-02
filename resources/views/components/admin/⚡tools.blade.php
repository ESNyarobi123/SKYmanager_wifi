<?php

use App\Models\Router;
use App\Services\MikrotikApiService;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    // ----- Port Tester -----
    #[Validate('required|string|max:253')]
    public string $portHost = '';

    #[Validate('required|integer|min:1|max:65535')]
    public int $portNumber = 80;

    public string $portProtocol = 'tcp';

    public ?array $portResult = null;

    public bool $portTesting = false;

    // ----- Ping Test -----
    #[Validate('required|string|max:253')]
    public string $pingHost = '';

    public ?string $selectedPingRouterId = null;

    public ?array $pingResult = null;

    public bool $pingTesting = false;

    public function mount(): void
    {
        $this->selectedPingRouterId = Router::where('is_online', true)->value('id');
    }

    public function routers()
    {
        return Router::orderBy('name')->get();
    }

    public function testPort(): void
    {
        $this->validateOnly('portHost');
        $this->validateOnly('portNumber');

        $this->portTesting = true;
        $this->portResult = null;
        $start = microtime(true);

        $fp = @stream_socket_client(
            $this->portProtocol.'://'.$this->portHost.':'.$this->portNumber,
            $errno,
            $errstr,
            5
        );

        $latency = round((microtime(true) - $start) * 1000, 2);

        if ($fp) {
            fclose($fp);
            $this->portResult = [
                'open' => true,
                'message' => "Port {$this->portNumber}/{$this->portProtocol} is OPEN on {$this->portHost}",
                'latency_ms' => $latency,
            ];
        } else {
            $this->portResult = [
                'open' => false,
                'message' => "Port {$this->portNumber}/{$this->portProtocol} is CLOSED on {$this->portHost} — {$errstr} ({$errno})",
                'latency_ms' => $latency,
            ];
        }

        $this->portTesting = false;
    }

    public function pingFromRouter(): void
    {
        $this->validateOnly('pingHost');

        if (! $this->selectedPingRouterId) {
            $this->pingResult = ['error' => 'No router selected.'];
            return;
        }

        $router = Router::find($this->selectedPingRouterId);
        if (! $router) {
            return;
        }

        $mikrotik = app(MikrotikApiService::class);
        $this->pingResult = null;
        $this->pingTesting = true;

        try {
            $mikrotik->connect($router);
            $results = $mikrotik->pingHost($this->pingHost, 5);
            $mikrotik->disconnect();

            $sent = count($results);
            $received = collect($results)->filter(fn ($r) => isset($r['time']))->count();
            $times = collect($results)
                ->filter(fn ($r) => isset($r['time']))
                ->map(fn ($r) => (int) str_replace('ms', '', $r['time']))
                ->values();

            $this->pingResult = [
                'host' => $this->pingHost,
                'sent' => $sent,
                'received' => $received,
                'loss_pct' => $sent > 0 ? round(($sent - $received) / $sent * 100) : 100,
                'avg_ms' => $times->avg() ? round($times->avg(), 2) : null,
                'min_ms' => $times->min(),
                'max_ms' => $times->max(),
                'rows' => $results,
            ];
        } catch (\Exception $e) {
            $this->pingResult = ['error' => $e->getMessage()];
        }

        $this->pingTesting = false;
    }
};
?>

<div>
    <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200 mb-6">Network Tools</h1>

    <div class="grid lg:grid-cols-2 gap-8">

        {{-- Port Tester --}}
        <div>
            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200 mb-4">🔌 TCP/UDP Port Tester</h2>
            <flux:card class="space-y-4">
                <flux:input wire:model="portHost" label="Host / IP" placeholder="192.168.88.1 or example.com" />
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="portNumber" label="Port" type="number" min="1" max="65535" />
                    <flux:select wire:model="portProtocol" label="Protocol">
                        <flux:select.option value="tcp">TCP</flux:select.option>
                        <flux:select.option value="udp">UDP</flux:select.option>
                    </flux:select>
                </div>

                {{-- Common ports quick-select --}}
                <div class="flex flex-wrap gap-1.5">
                    @foreach ([['80','HTTP'],['443','HTTPS'],['22','SSH'],['8728','MT-API'],['3306','MySQL'],['1812','RADIUS'],['53','DNS'],['21','FTP']] as [$p,$label])
                        <button wire:click="$set('portNumber', {{ $p }})"
                            class="text-xs px-2 py-1 rounded border border-zinc-200 dark:border-zinc-700 hover:border-purple-400 transition-colors">
                            {{ $label }} ({{ $p }})
                        </button>
                    @endforeach
                </div>

                <flux:button wire:click="testPort" wire:loading.attr="disabled"
                    wire:target="testPort" variant="primary" icon="signal" class="w-full">
                    <span wire:loading.remove wire:target="testPort">Test Port</span>
                    <span wire:loading wire:target="testPort">Testing...</span>
                </flux:button>

                @if ($portResult !== null)
                    <div class="p-3 rounded-lg border {{ $portResult['open'] ? 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800' : 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800' }}">
                        <div class="flex items-center gap-2 font-semibold {{ $portResult['open'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                            <span>{{ $portResult['open'] ? '✅' : '❌' }}</span>
                            <span>{{ $portResult['message'] }}</span>
                        </div>
                        <div class="text-xs text-zinc-500 mt-1">Latency: {{ $portResult['latency_ms'] }} ms</div>
                    </div>
                @endif
            </flux:card>
        </div>

        {{-- Router Ping Test --}}
        <div>
            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200 mb-4">📡 Ping Test (via Router)</h2>
            <flux:card class="space-y-4">
                <flux:select wire:model="selectedPingRouterId" label="Source Router">
                    @foreach ($this->routers() as $router)
                        <flux:select.option :value="$router->id">
                            {{ $router->name }} — {{ $router->ip_address }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="pingHost" label="Target Host / IP" placeholder="8.8.8.8 or google.com" />

                {{-- Common targets --}}
                <div class="flex flex-wrap gap-1.5">
                    @foreach (['8.8.8.8','1.1.1.1','google.com','facebook.com'] as $target)
                        <button wire:click="$set('pingHost', '{{ $target }}')"
                            class="text-xs px-2 py-1 rounded border border-zinc-200 dark:border-zinc-700 hover:border-purple-400 transition-colors">
                            {{ $target }}
                        </button>
                    @endforeach
                </div>

                <flux:button wire:click="pingFromRouter" wire:loading.attr="disabled"
                    wire:target="pingFromRouter" variant="primary" icon="arrow-path" class="w-full">
                    <span wire:loading.remove wire:target="pingFromRouter">Ping</span>
                    <span wire:loading wire:target="pingFromRouter">Pinging...</span>
                </flux:button>

                @if ($pingResult !== null)
                    @if (isset($pingResult['error']))
                        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-3 dark:bg-red-800/10 dark:border-red-900 dark:text-red-500 text-sm">{{ $pingResult['error'] }}</div>
                    @else
                        <div class="grid grid-cols-3 gap-2 text-center text-sm">
                            <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-2">
                                <div class="font-bold {{ $pingResult['loss_pct'] > 50 ? 'text-red-600' : ($pingResult['loss_pct'] > 0 ? 'text-amber-500' : 'text-green-600') }}">
                                    {{ $pingResult['loss_pct'] }}%
                                </div>
                                <div class="text-xs text-zinc-400">Packet Loss</div>
                            </div>
                            <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-2">
                                <div class="font-bold text-purple-700">{{ $pingResult['avg_ms'] ?? '—' }} ms</div>
                                <div class="text-xs text-zinc-400">Avg RTT</div>
                            </div>
                            <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-2">
                                <div class="font-bold">{{ $pingResult['received'] }}/{{ $pingResult['sent'] }}</div>
                                <div class="text-xs text-zinc-400">Received</div>
                            </div>
                        </div>
                    @endif
                @endif
            </flux:card>
        </div>

        {{-- Speed Test --}}
        <div class="lg:col-span-2">
            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200 mb-4">⚡ Internet Speed Test</h2>
            <flux:card>
                <div x-data="speedTest()" class="space-y-6">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800 p-4">
                            <div class="text-3xl font-bold text-purple-700" x-text="download || '—'"></div>
                            <div class="text-xs text-zinc-400 mt-1">Mbps Download</div>
                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5 mt-2">
                                <div class="h-1.5 rounded-full bg-purple-500 transition-all duration-300" :style="'width:'+Math.min(downloadPct,100)+'%'"></div>
                            </div>
                        </div>
                        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800 p-4">
                            <div class="text-3xl font-bold text-green-600" x-text="upload || '—'"></div>
                            <div class="text-xs text-zinc-400 mt-1">Mbps Upload</div>
                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5 mt-2">
                                <div class="h-1.5 rounded-full bg-green-500 transition-all duration-300" :style="'width:'+Math.min(uploadPct,100)+'%'"></div>
                            </div>
                        </div>
                        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800 p-4">
                            <div class="text-3xl font-bold text-zinc-700" x-text="latency || '—'"></div>
                            <div class="text-xs text-zinc-400 mt-1">ms Latency</div>
                        </div>
                    </div>

                    <div class="text-center">
                        <button @click="run()" :disabled="running"
                            :class="running ? 'opacity-60 cursor-not-allowed' : 'hover:bg-purple-700'"
                            class="px-8 py-3 rounded-xl bg-purple-800 text-white font-semibold transition-all">
                            <span x-show="!running">▶ Start Speed Test</span>
                            <span x-show="running" x-text="status"></span>
                        </button>
                        <p class="text-xs text-zinc-400 mt-2" x-show="note" x-text="note"></p>
                    </div>
                </div>
            </flux:card>
        </div>

    </div>
</div>

<script>
function speedTest() {
    return {
        download: null, upload: null, latency: null,
        downloadPct: 0, uploadPct: 0,
        running: false, status: '', note: '',

        async run() {
            this.running = true;
            this.download = null; this.upload = null; this.latency = null;
            this.downloadPct = 0; this.uploadPct = 0; this.note = '';

            // Latency
            this.status = 'Measuring latency...';
            const t0 = performance.now();
            await fetch('/speedtest/ping', { cache: 'no-store' });
            this.latency = Math.round(performance.now() - t0) + ' ms';

            // Download
            this.status = 'Testing download...';
            const dlStart = performance.now();
            const dlRes = await fetch('/speedtest/download?size=5', { cache: 'no-store' });
            const dlBlob = await dlRes.blob();
            const dlTime = (performance.now() - dlStart) / 1000;
            const dlMbps = ((dlBlob.size * 8) / dlTime / 1e6).toFixed(2);
            this.download = dlMbps;
            this.downloadPct = Math.min(parseFloat(dlMbps) / 100 * 100, 100);

            // Upload
            this.status = 'Testing upload...';
            const ulData = new Uint8Array(2 * 1024 * 1024);
            const ulStart = performance.now();
            await fetch('/speedtest/upload', { method: 'POST', body: ulData, cache: 'no-store' });
            const ulTime = (performance.now() - ulStart) / 1000;
            const ulMbps = ((ulData.byteLength * 8) / ulTime / 1e6).toFixed(2);
            this.upload = ulMbps;
            this.uploadPct = Math.min(parseFloat(ulMbps) / 100 * 100, 100);

            this.status = '';
            this.note = 'Results reflect speed between this browser and the server, not the end ISP.';
            this.running = false;
        }
    }
}
</script>
