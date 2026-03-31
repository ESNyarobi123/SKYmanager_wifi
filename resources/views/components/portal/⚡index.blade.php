<?php

use App\Models\BillingPlan;
use App\Models\Payment;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\WifiUser;
use App\Services\MikrotikApiService;
use App\Services\PaymentGatewayService;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Url]
    public string $mac = '';

    #[Url]
    public string $ip = '';

    #[Url(as: 'link-orig')]
    public string $linkOrig = '';

    #[Url]
    public string $identity = '';

    public string $step = 'plans';

    public ?string $selectedPlanId = null;

    #[Validate(['required', 'regex:/^(\+?255|0)[67]\d{8}$/'])]
    public string $phone = '';

    public ?string $transactionId = null;

    public ?string $orderReference = null;

    public ?string $detectedChannel = null;

    public ?string $errorMessage = null;

    public bool $isProcessing = false;

    public function mount(): void
    {
        $this->mac = strtoupper($this->mac);
    }

    public function plans()
    {
        return BillingPlan::where('is_active', true)->orderBy('price')->get();
    }

    public function selectPlan(string $planId): void
    {
        $this->selectedPlanId = $planId;
        $this->step = 'payment';
        $this->errorMessage = null;
    }

    public function back(): void
    {
        $this->step = 'plans';
        $this->errorMessage = null;
    }

    public function pay(PaymentGatewayService $gateway): void
    {
        $this->validate(['phone' => ['required', 'regex:/^(\+?255|0)[67]\d{8}$/']]);

        $plan = BillingPlan::findOrFail($this->selectedPlanId);
        $router = Router::where('is_online', true)->first();

        if (! $router) {
            $this->errorMessage = 'No router available. Please try again later.';

            return;
        }

        $this->isProcessing = true;
        $this->errorMessage = null;
        $this->orderReference = 'SKY-'.strtoupper(uniqid());

        try {
            $result = $gateway->initiatePayment($this->phone, (float) $plan->price, $this->orderReference);

            $wifiUser = WifiUser::firstOrCreate(
                ['mac_address' => $this->mac],
                ['phone_number' => $this->phone, 'is_active' => false]
            );

            $wifiUser->update(['phone_number' => $this->phone]);

            $subscription = Subscription::create([
                'wifi_user_id' => $wifiUser->id,
                'plan_id' => $plan->id,
                'router_id' => $router->id,
                'expires_at' => now()->addMinutes($plan->duration_minutes),
                'status' => 'pending',
            ]);

            Payment::create([
                'subscription_id' => $subscription->id,
                'amount' => $plan->price,
                'provider' => $result['channel'],
                'reference' => $this->orderReference,
                'transaction_id' => $result['transactionId'],
                'status' => 'pending',
            ]);

            $this->transactionId = $result['transactionId'];
            $this->detectedChannel = $result['channel'];
            $this->step = 'verifying';

        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isProcessing = false;
        }
    }

    public function verify(PaymentGatewayService $gateway, MikrotikApiService $mikrotik): void
    {
        if (! $this->transactionId) {
            return;
        }

        try {
            $result = $gateway->verifyTransaction($this->transactionId);

            $payment = Payment::where('transaction_id', $this->transactionId)->firstOrFail();
            $payment->update([
                'status' => $result['status'],
                'provider' => $result['channel'] ?? $payment->provider,
            ]);

            if ($result['status'] === 'success') {
                $subscription = $payment->subscription()->with(['wifiUser', 'plan', 'router'])->firstOrFail();

                $subscription->update(['status' => 'active']);

                try {
                    $mikrotik->connect($subscription->router)
                        ->addHotspotUser(
                            $subscription->wifiUser->mac_address,
                            substr(md5($subscription->wifiUser->mac_address), 0, 8),
                            $subscription->plan->name,
                            $subscription->wifiUser->mac_address
                        );
                    $mikrotik->disconnect();
                } catch (\Exception) {
                }

                $subscription->wifiUser->update(['is_active' => true]);
                $this->step = 'connected';
            } elseif ($result['status'] === 'failed') {
                $payment->subscription()->update(['status' => 'expired']);
                $this->step = 'payment';
                $this->errorMessage = 'Payment failed. Please try again.';
            } else {
                $this->errorMessage = 'Payment still processing. Please wait a moment and try again.';
            }
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }
};
?>

<div class="w-full max-w-sm">

    {{-- Header --}}
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-purple-800 mb-3">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-purple-900">SKYmanager WiFi</h1>
        <p class="text-sm text-gray-500 mt-1">Fast & Reliable Internet Access</p>
    </div>

    @if ($errorMessage)
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ $errorMessage }}
        </div>
    @endif

    {{-- Step: Plans --}}
    @if ($step === 'plans')
        <div class="space-y-3">
            @foreach ($this->plans() as $plan)
                <button wire:click="selectPlan('{{ $plan->id }}')" class="w-full text-left">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-purple-400 hover:shadow-md transition-all active:scale-95">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-semibold text-gray-900">{{ $plan->name }}</div>
                                @if ($plan->description)
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $plan->description }}</div>
                                @endif
                                <div class="flex gap-3 mt-2 text-xs text-gray-500">
                                    <span>⏱ {{ $plan->duration_minutes >= 60 ? round($plan->duration_minutes / 60, 1).'h' : $plan->duration_minutes.'m' }}</span>
                                    <span>⬆ {{ $plan->upload_limit }}Mbps</span>
                                    <span>⬇ {{ $plan->download_limit }}Mbps</span>
                                </div>
                            </div>
                            <div class="text-right ml-4">
                                <div class="text-xl font-bold text-purple-800">{{ number_format($plan->price, 0) }}</div>
                                <div class="text-xs text-gray-400">TZS</div>
                            </div>
                        </div>
                    </div>
                </button>
            @endforeach
        </div>
    @endif

    {{-- Step: Payment --}}
    @if ($step === 'payment')
        @php $plan = App\Models\BillingPlan::find($selectedPlanId); @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div>
                    <div class="font-semibold text-gray-900">{{ $plan?->name }}</div>
                    <div class="text-xs text-gray-500">
                        {{ $plan?->duration_minutes >= 60 ? round($plan->duration_minutes / 60, 1).'h' : $plan?->duration_minutes.'m' }}
                        &bull; {{ $plan?->download_limit }}Mbps down
                    </div>
                </div>
                <div class="text-xl font-bold text-purple-800">TZS {{ number_format($plan?->price ?? 0, 0) }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <input wire:model="phone" type="tel" placeholder="07XX XXX XXX"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-purple-500 focus:ring-1 focus:ring-purple-500 outline-none" />
                @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-2 p-3 bg-purple-50 rounded-lg border border-purple-100">
                <svg class="w-4 h-4 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-xs text-purple-700">Works with M-Pesa, Tigo Pesa &amp; Airtel Money. The right channel is detected automatically from your number.</p>
            </div>

            <button wire:click="pay" wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-not-allowed"
                class="w-full py-3 rounded-xl bg-purple-800 text-white font-semibold text-sm hover:bg-purple-700 active:scale-95 transition-all">
                <span wire:loading.remove>Pay TZS {{ number_format($plan?->price ?? 0, 0) }}</span>
                <span wire:loading>Processing...</span>
            </button>

            <button wire:click="back" class="w-full py-2 text-sm text-gray-500 hover:text-gray-700">← Back to plans</button>
        </div>
    @endif

    {{-- Step: Verifying --}}
    @if ($step === 'verifying')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center space-y-4">
            <div class="w-14 h-14 rounded-full bg-yellow-50 flex items-center justify-center mx-auto">
                <svg class="w-7 h-7 text-yellow-500 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Waiting for payment</p>
                <p class="text-sm text-gray-500 mt-1">Check your phone for a payment prompt{{ $detectedChannel ? ' via '.str_replace('-', ' ', $detectedChannel) : '' }}.</p>
                <p class="text-xs text-gray-400 mt-1 font-mono">Ref: {{ $orderReference }}</p>
            </div>
            <button wire:click="verify" wire:loading.attr="disabled"
                class="w-full py-3 rounded-xl bg-purple-800 text-white font-semibold text-sm hover:bg-purple-700 transition-all">
                <span wire:loading.remove>I've Paid – Verify</span>
                <span wire:loading>Checking...</span>
            </button>
            @if ($errorMessage)
                <p class="text-xs text-orange-600">{{ $errorMessage }}</p>
            @endif
        </div>
    @endif

    {{-- Step: Connected --}}
    @if ($step === 'connected')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center space-y-4">
            <div class="w-16 h-16 rounded-full bg-green-50 flex items-center justify-center mx-auto">
                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div>
                <p class="text-xl font-bold text-green-700">You're Connected!</p>
                <p class="text-sm text-gray-500 mt-1">Enjoy your internet access.</p>
            </div>
            @if ($linkOrig)
                <a href="{{ $linkOrig }}"
                    class="block w-full py-3 rounded-xl bg-purple-800 text-white font-semibold text-sm text-center hover:bg-purple-700 transition-all">
                    Continue Browsing
                </a>
            @endif
        </div>
    @endif

</div>