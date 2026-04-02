<?php

namespace App\Livewire\Customer;

use App\Models\ActivityLog;
use App\Models\CustomerPaymentGateway;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class PaymentSettings extends Component
{
    #[Validate('required|string|min:10|max:200')]
    public string $consumerKey = '';

    #[Validate('required|string|min:10|max:200')]
    public string $consumerSecret = '';

    #[Validate('nullable|string|max:100')]
    public string $accountNumber = '';

    public bool $keysEditable = false;

    public bool $saved = false;

    public ?bool $testPassed = null;

    public string $testMessage = '';

    public bool $testing = false;

    public function mount(): void
    {
        $gateway = $this->gateway;

        if ($gateway) {
            $this->accountNumber = $gateway->account_number ?? '';
        }
    }

    #[Computed]
    public function customer(): User
    {
        return auth()->user();
    }

    #[Computed]
    public function gateway(): ?CustomerPaymentGateway
    {
        return $this->customer->clickpesaGateway();
    }

    public function save(): void
    {
        $rules = [
            'accountNumber' => 'nullable|string|max:100',
        ];

        if ($this->keysEditable || ! $this->gateway) {
            $rules['consumerKey'] = 'required|string|min:10|max:200';
            $rules['consumerSecret'] = 'required|string|min:10|max:200';
        }

        $this->validate($rules);

        $data = [
            'account_number' => $this->accountNumber ?: null,
            'is_active' => true,
        ];

        if ($this->keysEditable || ! $this->gateway) {
            $data['consumer_key'] = $this->consumerKey;
            $data['consumer_secret'] = $this->consumerSecret;
            $data['verified_at'] = null;
        }

        $gateway = CustomerPaymentGateway::updateOrCreate(
            ['customer_id' => $this->customer->id, 'gateway' => 'clickpesa'],
            $data
        );

        ActivityLog::record(
            'Customer updated ClickPesa payment gateway',
            $gateway,
            $this->customer
        );

        $this->consumerKey = '';
        $this->consumerSecret = '';
        $this->keysEditable = false;
        $this->testPassed = null;
        $this->testMessage = '';
        $this->saved = true;

        unset($this->gateway);
    }

    public function testConnection(): void
    {
        $gateway = $this->gateway;

        if (! $gateway || ! $gateway->isConfigured()) {
            $this->testPassed = false;
            $this->testMessage = __('Please save your credentials first before testing.');

            return;
        }

        $this->testing = true;
        $this->testPassed = null;
        $this->testMessage = '';

        try {
            $response = Http::timeout(10)
                ->post('https://api.clickpesa.com/third-parties/non-trade/users/generate-token', [
                    'clientId' => $gateway->consumer_key,
                    'apiKey' => $gateway->consumer_secret,
                ]);

            if ($response->successful() && $response->json('token')) {
                $gateway->update(['verified_at' => now()]);
                unset($this->gateway);

                ActivityLog::record(
                    'Customer verified ClickPesa connection successfully',
                    $gateway,
                    $this->customer
                );

                $this->testPassed = true;
                $this->testMessage = __('Connection successful! Your ClickPesa account is connected.');
            } else {
                $body = $response->json();
                $this->testPassed = false;
                $this->testMessage = $body['message'] ?? $body['error'] ?? __('Connection failed. Check your credentials and try again.');
            }
        } catch (\Exception $e) {
            $this->testPassed = false;
            $this->testMessage = __('Connection timed out. Please check your internet connection and try again.');
        }

        $this->testing = false;
    }

    public function disableGateway(): void
    {
        $gateway = $this->gateway;

        if ($gateway) {
            $gateway->update(['is_active' => false, 'verified_at' => null]);
            unset($this->gateway);

            ActivityLog::record(
                'Customer disabled ClickPesa payment gateway',
                $gateway,
                $this->customer
            );
        }

        $this->testPassed = null;
        $this->testMessage = '';
        $this->saved = false;
        session()->flash('gateway_disabled', true);
    }

    public function enableEdit(): void
    {
        $this->keysEditable = true;
        $this->consumerKey = '';
        $this->consumerSecret = '';
    }

    public function render()
    {
        return view('livewire.customer.payment-settings');
    }
}
