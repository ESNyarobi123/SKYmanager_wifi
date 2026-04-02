<?php

use App\Models\Router;
use App\Services\MikrotikApiService;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public bool $showModal = false;

    public bool $showScriptModal = false;

    public ?string $editingId = null;

    public string $provisioningScript = '';

    public ?string $scriptRouterName = null;

    public bool $showFullScriptModal = false;

    public string $fullSetupScript = '';

    public ?string $fullScriptRouterName = null;

    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('required|ip')]
    public string $ipAddress = '';

    #[Validate('required|integer|min:1|max:65535')]
    public int $apiPort = 8728;

    #[Validate('required|string|max:100')]
    public string $apiUsername = '';

    #[Validate('required|string|max:100')]
    public string $apiPassword = '';

    #[Validate('nullable|string|max:20')]
    public string $wgAddress = '';

    #[Validate('nullable|string|max:64')]
    public string $hotspotSsid = '';

    #[Validate('nullable|string|max:64')]
    public string $hotspotInterface = '';

    #[Validate('nullable|string|max:15')]
    public string $hotspotGateway = '';

    #[Validate('nullable|string|max:18')]
    public string $hotspotNetwork = '';

    public function routers()
    {
        return Router::latest()->get();
    }

    public function openModal(?string $id = null): void
    {
        $this->reset(['name', 'ipAddress', 'apiPort', 'apiUsername', 'apiPassword', 'wgAddress', 'hotspotSsid', 'hotspotInterface', 'hotspotGateway', 'hotspotNetwork']);
        $this->apiPort = 8728;
        $this->hotspotSsid = 'PEACE';
        $this->hotspotInterface = 'bridge';
        $this->hotspotGateway = '192.168.88.1';
        $this->hotspotNetwork = '192.168.88.0/24';
        $this->editingId = $id;

        if ($id) {
            $router = Router::findOrFail($id);
            $this->name = $router->name;
            $this->ipAddress = $router->ip_address;
            $this->apiPort = $router->api_port;
            $this->apiUsername = $router->api_username;
            $this->apiPassword = $router->api_password;
            $this->wgAddress = $router->wg_address ?? '';
            $this->hotspotSsid = $router->hotspot_ssid ?? 'PEACE';
            $this->hotspotInterface = $router->hotspot_interface ?? 'bridge';
            $this->hotspotGateway = $router->hotspot_gateway ?? '192.168.88.1';
            $this->hotspotNetwork = $router->hotspot_network ?? '192.168.88.0/24';
        }

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'               => $this->name,
            'ip_address'         => $this->ipAddress,
            'api_port'           => $this->apiPort,
            'api_username'       => $this->apiUsername,
            'api_password'       => $this->apiPassword,
            'wg_address'         => $this->wgAddress ?: null,
            'hotspot_ssid'       => $this->hotspotSsid ?: 'PEACE',
            'hotspot_interface'  => $this->hotspotInterface ?: 'bridge',
            'hotspot_gateway'    => $this->hotspotGateway ?: '192.168.88.1',
            'hotspot_network'    => $this->hotspotNetwork ?: '192.168.88.0/24',
        ];

        if ($this->editingId) {
            Router::findOrFail($this->editingId)->update($data);
            session()->flash('status', 'Router updated.');
        } else {
            Router::create($data);
            session()->flash('status', 'Router created.');
        }

        $this->showModal = false;
        $this->reset(['name', 'ipAddress', 'apiPort', 'apiUsername', 'apiPassword', 'wgAddress', 'hotspotSsid', 'hotspotInterface', 'hotspotGateway', 'hotspotNetwork']);
    }

    public function delete(string $id): void
    {
        Router::findOrFail($id)->delete();
        session()->flash('status', 'Router deleted.');
    }

    public function testConnection(string $id, MikrotikApiService $mikrotik): void
    {
        $router = Router::findOrFail($id);

        try {
            $mikrotik->connect($router);
            $vpnConnected = false;
            try {
                $vpnConnected = $mikrotik->checkVpnStatus();
            } catch (\Exception) {
            }
            $mikrotik->disconnect();
            $router->update(['is_online' => true, 'vpn_connected' => $vpnConnected, 'last_seen' => now()]);
            $vpnLabel = $vpnConnected ? ' | VPN: Connected' : ' | VPN: Disconnected';
            session()->flash('status', "Router [{$router->name}] is online{$vpnLabel}.");
        } catch (\Exception $e) {
            $router->update(['is_online' => false, 'vpn_connected' => false]);
            session()->flash('error', "Router [{$router->name}] is offline: {$e->getMessage()}");
        }
    }

    public function generateScript(string $id, MikrotikApiService $mikrotik): void
    {
        $router = Router::findOrFail($id);

        try {
            $this->provisioningScript = $mikrotik->generateProvisioningScript($router);
            $this->scriptRouterName = $router->name;
            $this->showScriptModal = true;
        } catch (\Exception $e) {
            session()->flash('error', "Failed to generate script: {$e->getMessage()}");
        }
    }

    public function generateFullSetupScript(string $id, MikrotikApiService $mikrotik): void
    {
        $router = Router::findOrFail($id);

        try {
            $this->fullSetupScript = $mikrotik->generateFullSetupScript($router);
            $this->fullScriptRouterName = $router->name;
            $this->showFullScriptModal = true;
        } catch (\Exception $e) {
            session()->flash('error', "Failed to generate full setup script: {$e->getMessage()}");
        }
    }

    public function applyCpdFix(string $id, MikrotikApiService $mikrotik): void
    {
        $router = Router::findOrFail($id);
        $portalUrl = config('app.url').'/portal';

        try {
            $mikrotik->connect($router);
            $mikrotik->configureCaptivePortal($portalUrl);
            $mikrotik->disconnect();
            $router->update(['is_online' => true, 'last_seen' => now()]);
            session()->flash('status', "CPD fix applied to [{$router->name}]. login.html uploaded, hotspot profile updated.");
        } catch (\Exception $e) {
            session()->flash('error', "CPD fix failed on [{$router->name}]: {$e->getMessage()}");
        }
    }
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">Routers</h1>
        <flux:button wire:click="openModal" icon="plus" variant="primary">Add Router</flux:button>
    </div>

    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4 dark:bg-emerald-800/10 dark:border-emerald-900 dark:text-emerald-500 mb-4" role="alert">
            <div class="flex gap-x-3"><x-lucide name="check-circle" class="size-4 shrink-0 mt-0.5"/><p class="text-sm">{{ session('status') }}</p></div>
        </div>
    @endif
    @if (session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 dark:bg-red-800/10 dark:border-red-900 dark:text-red-500 mb-4" role="alert">
            <div class="flex gap-x-3"><x-lucide name="x-circle" class="size-4 shrink-0 mt-0.5"/><p class="text-sm">{{ session('error') }}</p></div>
        </div>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>IP Address</flux:table.column>
            <flux:table.column>Port</flux:table.column>
            <flux:table.column>API Status</flux:table.column>
            <flux:table.column>VPN Tunnel</flux:table.column>
            <flux:table.column>Last Seen</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->routers() as $router)
                <flux:table.row :key="$router->id">
                    <flux:table.cell class="font-semibold">{{ $router->name }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-sm">{{ $router->ip_address }}</flux:table.cell>
                    <flux:table.cell>{{ $router->api_port }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($router->is_online)
                            <flux:badge color="green" size="sm" icon="check-circle">Online</flux:badge>
                        @else
                            <flux:badge color="red" size="sm" icon="x-circle">Offline</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($router->vpn_connected)
                            <flux:badge color="lime" size="sm" icon="lock-closed">Connected</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm" icon="lock-open">Disconnected</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-sm text-zinc-500">
                        {{ $router->last_seen?->diffForHumans() ?? 'Never' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex flex-wrap gap-2">
                            <flux:button size="sm" icon="signal"
                                wire:click="testConnection('{{ $router->id }}')"
                                wire:loading.attr="disabled"
                                wire:target="testConnection('{{ $router->id }}')">Test</flux:button>
                            <flux:button size="sm" icon="code-bracket" variant="filled"
                                wire:click="generateFullSetupScript('{{ $router->id }}')"
                                wire:loading.attr="disabled"
                                wire:target="generateFullSetupScript('{{ $router->id }}')">
                                <span wire:loading.remove wire:target="generateFullSetupScript('{{ $router->id }}')">Full Setup</span>
                                <span wire:loading wire:target="generateFullSetupScript('{{ $router->id }}')">...</span>
                            </flux:button>
                            <flux:button size="sm" icon="command-line"
                                wire:click="generateScript('{{ $router->id }}')"
                                wire:loading.attr="disabled"
                                wire:target="generateScript('{{ $router->id }}')">ZTP</flux:button>
                            <flux:button size="sm" icon="wifi" variant="primary"
                                wire:click="applyCpdFix('{{ $router->id }}')"
                                wire:loading.attr="disabled"
                                wire:target="applyCpdFix('{{ $router->id }}')"
                                wire:confirm="Apply CPD fix to {{ $router->name }}? This uploads login.html and updates the hotspot profile.">
                                <span wire:loading.remove wire:target="applyCpdFix('{{ $router->id }}')">CPD Fix</span>
                                <span wire:loading wire:target="applyCpdFix('{{ $router->id }}')">...</span>
                            </flux:button>
                            <flux:button size="sm" icon="pencil" wire:click="openModal('{{ $router->id }}')">Edit</flux:button>
                            <flux:button size="sm" icon="trash" variant="danger"
                                wire:click="delete('{{ $router->id }}')"
                                wire:confirm="Delete this router?">Delete</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    {{-- Add / Edit Router Modal --}}
    <flux:modal wire:model="showModal" class="w-full max-w-2xl">
        <div class="space-y-6">
            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">{{ $editingId ? 'Edit Router' : 'Add Router' }}</h2>
            <div class="grid grid-cols-1 gap-4">
                <p class="text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wide">API Connection</p>
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="name" label="Name" placeholder="HQ Router" class="col-span-2" />
                    <flux:input wire:model="ipAddress" label="IP Address" placeholder="192.168.88.1" />
                    <flux:input wire:model="apiPort" label="API Port" type="number" placeholder="8728" />
                    <flux:input wire:model="apiUsername" label="API Username" placeholder="sky-api" />
                    <flux:input wire:model="apiPassword" label="API Password" type="password" />
                </div>
                <flux:separator />
                <p class="text-xs font-semibold text-gray-500 dark:text-neutral-400 uppercase tracking-wide">WireGuard &amp; Hotspot (kwa Full Setup Script)</p>
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="wgAddress" label="WireGuard Tunnel IP" placeholder="10.10.0.2/32" />
                    <flux:input wire:model="hotspotSsid" label="WiFi SSID" placeholder="PEACE" />
                    <flux:input wire:model="hotspotInterface" label="Bridge Interface" placeholder="bridge" />
                    <flux:input wire:model="hotspotGateway" label="Hotspot Gateway IP" placeholder="192.168.88.1" />
                    <flux:input wire:model="hotspotNetwork" label="Hotspot Network" placeholder="192.168.88.0/24" class="col-span-2" />
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="save" variant="primary">Save</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Full MikroTik Setup Script Modal --}}
    <flux:modal wire:model="showFullScriptModal" class="w-full max-w-4xl">
        <div class="space-y-4"
            x-data="{ copied: false, copy() {
                navigator.clipboard.writeText(this.$refs.fullscript.innerText)
                    .then(() => { this.copied = true; setTimeout(() => this.copied = false, 3000); });
            } }">

            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">Full MikroTik Setup Script</h2>
                    <p class="text-sm text-gray-500 dark:text-neutral-500">{{ $fullScriptRouterName }} &mdash; Bandika kwenye MikroTik → New Terminal</p>
                </div>
                <button @click="copy()"
                    class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="copied
                        ? 'border-green-300 bg-green-50 text-green-700'
                        : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200'">
                    <svg x-show="!copied" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <svg x-show="copied" class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span x-text="copied ? '✅ Nakiliwa!' : 'Nakili Script'"></span>
                </button>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20 p-3 text-xs text-blue-800 dark:text-blue-300">
                    <div class="font-semibold mb-1">📋 Hatua 3 baada ya script:</div>
                    <ol class="list-decimal list-inside space-y-0.5">
                        <li>Run script kwenye New Terminal</li>
                        <li>Nakili WireGuard Public Key</li>
                        <li>Ongeza kwenye VPS kama peer</li>
                    </ol>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20 p-3 text-xs text-amber-800 dark:text-amber-300">
                    <div class="font-semibold mb-1">⚠️ Angalizo:</div>
                    <p>Badilisha <code class="font-mono">wifiIface</code> na <code class="font-mono">wanIface</code> kulingana na router yako (juu ya script).</p>
                </div>
                <div class="rounded-lg border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20 p-3 text-xs text-green-800 dark:text-green-300">
                    <div class="font-semibold mb-1">✅ Script inafanya:</div>
                    <p>WireGuard, Bridge, Hotspot, CPD, DNS Spoofing, DHCP Opt114, Walled Garden, API User, Firewall.</p>
                </div>
            </div>

            <div class="rounded-xl bg-zinc-950 border border-zinc-800 overflow-auto max-h-[60vh]">
                <pre x-ref="fullscript" class="p-4 text-xs leading-relaxed text-zinc-100 font-mono whitespace-pre">{{ $fullSetupScript }}</pre>
            </div>

            <div class="flex justify-end gap-2">
                <button @click="copy()"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-4 py-2 text-sm font-medium transition-colors"
                    :class="copied
                        ? 'border-green-300 bg-green-50 text-green-700'
                        : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200'">
                    <span x-text="copied ? '✅ Nakiliwa!' : '📋 Nakili Script'"></span>
                </button>
                <flux:button wire:click="$set('showFullScriptModal', false)" variant="primary">Funga</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ZTP Provisioning Script Modal --}}
    <flux:modal wire:model="showScriptModal" class="w-full max-w-3xl">
        <div class="space-y-4"
            x-data="{ copied: false, copy() {
                navigator.clipboard.writeText(this.$refs.script.innerText)
                    .then(() => { this.copied = true; setTimeout(() => this.copied = false, 2500); });
            } }">

            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">Zero-Touch Provisioning Script</h2>
                    <p class="text-sm text-gray-500 dark:text-neutral-500">{{ $scriptRouterName }} &mdash; paste into RouterOS terminal</p>
                </div>
                <button @click="copy()"
                    class="shrink-0 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="copied
                        ? 'border-green-300 bg-green-50 text-green-700'
                        : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200'">
                    <svg x-show="!copied" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <svg x-show="copied" class="h-3.5 w-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                </button>
            </div>

            <div class="rounded-xl bg-zinc-950 border border-zinc-800 overflow-auto max-h-[60vh]">
                <pre x-ref="script" class="p-4 text-xs leading-relaxed text-zinc-100 font-mono whitespace-pre">{{ $provisioningScript }}</pre>
            </div>

            <div class="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20 p-3 text-xs text-amber-800 dark:text-amber-300">
                <strong>⚠ Note:</strong> This script creates a new API user <code class="font-mono">sky-api</code>
                and removes the default <code class="font-mono">admin</code> account.
                The API password has been saved to this router record and VPN credentials must be pre-configured on the VPS PPP server.
            </div>

            <div class="flex justify-end">
                <flux:button wire:click="$set('showScriptModal', false)" variant="ghost">Close</flux:button>
            </div>
        </div>
    </flux:modal>
</div>