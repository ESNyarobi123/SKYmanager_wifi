<?php

use App\Models\Setting;
use Livewire\Component;

new class extends Component
{
    public string $companyName = '';
    public string $companyEmail = '';
    public string $companyPhone = '';
    public string $defaultSsid = '';
    public string $timezone = '';
    public string $smsGateway = '';
    public string $smsApiKey = '';
    public string $smsSenderId = '';
    public int $referralRewardDays = 1;
    public string $portalWelcomeMessage = '';
    public bool $maintenanceMode = false;

    public bool $saved = false;

    public function mount(): void
    {
        $this->companyName = Setting::get('company_name', 'SKYmanager');
        $this->companyEmail = Setting::get('company_email', '');
        $this->companyPhone = Setting::get('company_phone', '');
        $this->defaultSsid = Setting::get('default_ssid', 'SKYMANAGER-WIFI');
        $this->timezone = Setting::get('timezone', 'Africa/Dar_es_Salaam');
        $this->smsGateway = Setting::get('sms_gateway', 'none');
        $this->smsApiKey = Setting::get('sms_api_key', '');
        $this->smsSenderId = Setting::get('sms_sender_id', 'SKYmanager');
        $this->referralRewardDays = (int) Setting::get('referral_reward_days', 1);
        $this->portalWelcomeMessage = Setting::get('portal_welcome_message', '');
        $this->maintenanceMode = (bool) Setting::get('maintenance_mode', false);
    }

    public function saveGeneral(): void
    {
        $this->validate([
            'companyName' => 'required|string|max:100',
            'companyEmail' => 'nullable|email|max:150',
            'companyPhone' => 'nullable|string|max:20',
            'defaultSsid' => 'required|string|max:64',
            'timezone' => 'required|string|max:60',
            'portalWelcomeMessage' => 'nullable|string|max:500',
        ]);

        Setting::set('company_name', $this->companyName, 'general', 'Company Name');
        Setting::set('company_email', $this->companyEmail, 'general', 'Company Email');
        Setting::set('company_phone', $this->companyPhone, 'general', 'Company Phone');
        Setting::set('default_ssid', $this->defaultSsid, 'network', 'Default SSID');
        Setting::set('timezone', $this->timezone, 'general', 'Timezone');
        Setting::set('portal_welcome_message', $this->portalWelcomeMessage, 'portal', 'Portal Welcome Message');

        $this->flashSaved();
    }

    public function saveSms(): void
    {
        $this->validate([
            'smsGateway' => 'required|in:none,wasiliana,beem,vonage',
            'smsApiKey' => 'nullable|string|max:200',
            'smsSenderId' => 'nullable|string|max:20',
        ]);

        Setting::set('sms_gateway', $this->smsGateway, 'sms', 'SMS Gateway');
        Setting::set('sms_api_key', $this->smsApiKey, 'sms', 'SMS API Key');
        Setting::set('sms_sender_id', $this->smsSenderId, 'sms', 'SMS Sender ID');

        $this->flashSaved();
    }

    public function saveReferral(): void
    {
        $this->validate([
            'referralRewardDays' => 'required|integer|min:0|max:30',
        ]);

        Setting::set('referral_reward_days', $this->referralRewardDays, 'referral', 'Referral Reward Days');

        $this->flashSaved();
    }

    public function toggleMaintenance(): void
    {
        $this->maintenanceMode = ! $this->maintenanceMode;
        Setting::set('maintenance_mode', $this->maintenanceMode, 'general', 'Maintenance Mode');
        $this->flashSaved();
    }

    private function flashSaved(): void
    {
        $this->saved = true;
        $this->dispatch('setting-saved');
    }
};
?>

<div x-data="{ tab: 'general' }">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('System Settings') }}</h1>
            <p class="text-sm text-gray-500 dark:text-neutral-500 mt-1">{{ __('Global branding, SMS gateway, referral & portal configuration') }}</p>
        </div>
        @if($saved)
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-3 dark:bg-emerald-800/10 dark:border-emerald-900 dark:text-emerald-500"
                 x-on:setting-saved.window="setTimeout(() => $wire.saved = false, 3000)" role="alert">
                <div class="flex gap-x-3"><x-lucide name="check-circle" class="size-4 shrink-0 mt-0.5"/><p class="text-sm">{{ __('Settings saved successfully.') }}</p></div>
            </div>
        @endif
    </div>

    {{-- ── Tab Nav ─────────────────────────────────────────────────────────── --}}
    <div class="flex gap-1 mb-6 border-b border-zinc-200 dark:border-zinc-700">
        @foreach([['general', 'cog-6-tooth', __('General')], ['sms', 'device-phone-mobile', __('SMS Gateway')], ['referral', 'users', __('Referral')], ['portal', 'globe-alt', __('Portal')]] as [$tab, $icon, $label])
            <button
                x-on:click="tab = '{{ $tab }}'"
                :class="tab === '{{ $tab }}' ? 'border-b-2 border-purple-500 text-purple-600 dark:text-purple-400 font-semibold' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="flex items-center gap-1.5 px-4 py-2.5 text-sm transition-colors"
            >
                <x-lucide name="{{ $icon }}" class="size-4"/>
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ── General ─────────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'general'" x-transition>
        <flux:card class="space-y-5 max-w-2xl">
            <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">{{ __('Company & Branding') }}</h2>

            <flux:field>
                <flux:label>{{ __('Company Name') }}</flux:label>
                <flux:input wire:model="companyName" placeholder="SKYmanager" />
                <flux:error name="companyName" />
            </flux:field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Support Email') }}</flux:label>
                    <flux:input wire:model="companyEmail" type="email" placeholder="support@example.co.tz" />
                    <flux:error name="companyEmail" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Support Phone') }}</flux:label>
                    <flux:input wire:model="companyPhone" placeholder="+255 700 000 000" />
                    <flux:error name="companyPhone" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Default Router SSID') }}</flux:label>
                    <flux:input wire:model="defaultSsid" placeholder="SKYMANAGER-WIFI" />
                    <flux:description>{{ __('Pre-filled when creating new routers.') }}</flux:description>
                    <flux:error name="defaultSsid" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Timezone') }}</flux:label>
                    <flux:select wire:model="timezone">
                        <flux:select.option value="Africa/Dar_es_Salaam">Africa/Dar_es_Salaam (EAT)</flux:select.option>
                        <flux:select.option value="Africa/Nairobi">Africa/Nairobi (EAT)</flux:select.option>
                        <flux:select.option value="Africa/Kampala">Africa/Kampala (EAT)</flux:select.option>
                        <flux:select.option value="UTC">UTC</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <flux:separator />

            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Maintenance Mode') }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Disables customer portal login while you update the system.') }}</p>
                </div>
                <flux:switch
                    wire:model="maintenanceMode"
                    wire:change="toggleMaintenance"
                    :checked="$maintenanceMode"
                />
            </div>

            <div class="flex justify-end">
                <flux:button wire:click="saveGeneral" variant="primary" icon="check">{{ __('Save General Settings') }}</flux:button>
            </div>
        </flux:card>
    </div>

    {{-- ── SMS Gateway ──────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'sms'" x-transition>
        <flux:card class="space-y-5 max-w-2xl">
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">{{ __('SMS Gateway') }}</h2>
                <p class="text-sm text-gray-500 dark:text-neutral-400 mt-1">{{ __('Used for OTP verification and subscription alerts.') }}</p>
            </div>

            <flux:field>
                <flux:label>{{ __('Gateway Provider') }}</flux:label>
                <flux:select wire:model="smsGateway">
                    <flux:select.option value="none">{{ __('Disabled') }}</flux:select.option>
                    <flux:select.option value="beem">Beem Africa (Tanzania)</flux:select.option>
                    <flux:select.option value="wasiliana">Wasiliana (Tanzania)</flux:select.option>
                    <flux:select.option value="vonage">Vonage (International)</flux:select.option>
                </flux:select>
            </flux:field>

            @if($smsGateway !== 'none')
                <flux:field>
                    <flux:label>{{ __('API Key / Token') }}</flux:label>
                    <flux:input wire:model="smsApiKey" type="password" placeholder="{{ __('Paste your API key') }}" />
                    <flux:error name="smsApiKey" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Sender ID') }}</flux:label>
                    <flux:input wire:model="smsSenderId" placeholder="SKYmanager" maxlength="11" />
                    <flux:description>{{ __('Max 11 characters, no spaces.') }}</flux:description>
                    <flux:error name="smsSenderId" />
                </flux:field>
            @endif

            <div class="flex justify-end">
                <flux:button wire:click="saveSms" variant="primary" icon="check">{{ __('Save SMS Settings') }}</flux:button>
            </div>
        </flux:card>
    </div>

    {{-- ── Referral ─────────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'referral'" x-transition>
        <flux:card class="space-y-5 max-w-2xl">
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">{{ __('Referral Program') }}</h2>
                <p class="text-sm text-gray-500 dark:text-neutral-400 mt-1">{{ __('Configure the referral reward given to both parties on successful sign-up.') }}</p>
            </div>

            <div class="rounded-xl bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800 p-4 text-sm text-sky-700 dark:text-sky-400">
                {{ __('When a new customer signs up using a referral link, both the referrer and the referred customer receive') }}
                <strong>{{ $referralRewardDays }} {{ trans_choice('day|days', $referralRewardDays) }}</strong>
                {{ __('added to their active subscription.') }}
            </div>

            <flux:field>
                <flux:label>{{ __('Reward Days') }}</flux:label>
                <flux:input wire:model="referralRewardDays" type="number" min="0" max="30" />
                <flux:description>{{ __('Set to 0 to disable the referral program entirely.') }}</flux:description>
                <flux:error name="referralRewardDays" />
            </flux:field>

            <div class="flex justify-end">
                <flux:button wire:click="saveReferral" variant="primary" icon="check">{{ __('Save Referral Settings') }}</flux:button>
            </div>
        </flux:card>
    </div>

    {{-- ── Portal ────────────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'portal'" x-transition>
        <flux:card class="space-y-5 max-w-2xl">
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-neutral-200">{{ __('Customer Portal') }}</h2>
                <p class="text-sm text-gray-500 dark:text-neutral-400 mt-1">{{ __('Text shown on the customer dashboard welcome screen.') }}</p>
            </div>

            <flux:field>
                <flux:label>{{ __('Welcome Message') }}</flux:label>
                <flux:textarea wire:model="portalWelcomeMessage" rows="3" placeholder="{{ __('e.g. Welcome to Our WiFi Service! Contact us on 0712 000 000 for support.') }}" />
                <flux:error name="portalWelcomeMessage" />
            </flux:field>

            <div class="flex justify-end">
                <flux:button wire:click="saveGeneral" variant="primary" icon="check">{{ __('Save Portal Settings') }}</flux:button>
            </div>
        </flux:card>
    </div>
</div>
