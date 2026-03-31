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

    public function routers()
    {
        return Router::latest()->get();
    }

    public function openModal(?string $id = null): void
    {
        $this->reset(['name', 'ipAddress', 'apiPort', 'apiUsername', 'apiPassword']);
        $this->apiPort = 8728;
        $this->editingId = $id;

        if ($id) {
            $router = Router::findOrFail($id);
            $this->name = $router->name;
            $this->ipAddress = $router->ip_address;
            $this->apiPort = $router->api_port;
            $this->apiUsername = $router->api_username;
            $this->apiPassword = $router->api_password;
        }

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'ip_address' => $this->ipAddress,
            'api_port' => $this->apiPort,
            'api_username' => $this->apiUsername,
            'api_password' => $this->apiPassword,
        ];

        if ($this->editingId) {
            Router::findOrFail($this->editingId)->update($data);
            session()->flash('status', 'Router updated.');
        } else {
            Router::create($data);
            session()->flash('status', 'Router created.');
        }

        $this->showModal = false;
        $this->reset(['name', 'ipAddress', 'apiPort', 'apiUsername', 'apiPassword']);
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
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Routers</flux:heading>
        <flux:button wire:click="openModal" icon="plus" variant="primary">Add Router</flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('status') }}</flux:callout>
    @endif
    @if (session('error'))
        <flux:callout variant="danger" icon="x-circle" class="mb-4">{{ session('error') }}</flux:callout>
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
                            <flux:button size="sm" icon="command-line" variant="filled"
                                wire:click="generateScript('{{ $router->id }}')"
                                wire:loading.attr="disabled"
                                wire:target="generateScript('{{ $router->id }}')">Setup</flux:button>
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
    <flux:modal wire:model="showModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? 'Edit Router' : 'Add Router' }}</flux:heading>
            <div class="flex flex-col gap-4">
                <flux:input wire:model="name" label="Name" placeholder="HQ Router" />
                <flux:input wire:model="ipAddress" label="IP Address" placeholder="192.168.88.1" />
                <flux:input wire:model="apiPort" label="API Port" type="number" placeholder="8728" />
                <flux:input wire:model="apiUsername" label="API Username" placeholder="admin" />
                <flux:input wire:model="apiPassword" label="API Password" type="password" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="save" variant="primary">Save</flux:button>
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
                    <flux:heading size="lg">Zero-Touch Provisioning Script</flux:heading>
                    <flux:subheading>{{ $scriptRouterName }} &mdash; paste into RouterOS terminal</flux:subheading>
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