<?php

use App\Livewire\Customer\PaymentSettings;
use App\Models\Customer;
use App\Models\CustomerBillingPlan;
use App\Models\CustomerPaymentGateway;
use App\Models\HotspotPayment;
use App\Models\Router;
use App\Models\User;
use App\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Page access ───────────────────────────────────────────────────────────────

test('guest cannot access payment settings page', function () {
    $this->get(route('customer.payment-settings'))->assertRedirect(route('customer.login'));
});

test('customer can access payment settings page', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer)
        ->get(route('customer.payment-settings'))
        ->assertOk();
});

// ── CustomerPaymentGateway model ──────────────────────────────────────────────

test('masked consumer key shows only last 6 chars', function () {
    $gateway = new CustomerPaymentGateway;
    $gateway->consumer_key = 'ABCDEFGHIJ123456';

    expect($gateway->maskedConsumerKey())->toBe('••••••••123456');
});

test('masked consumer secret shows only last 6 chars', function () {
    $gateway = new CustomerPaymentGateway;
    $gateway->consumer_secret = 'SECRETKEY789xyz';

    expect($gateway->maskedConsumerSecret())->toBe('••••••••789xyz');
});

test('isConfigured returns false when inactive', function () {
    $gateway = new CustomerPaymentGateway;
    $gateway->is_active = false;
    $gateway->consumer_key = 'somekey';
    $gateway->consumer_secret = 'somesecret';

    expect($gateway->isConfigured())->toBeFalse();
});

test('isConfigured returns false when keys are blank', function () {
    $gateway = new CustomerPaymentGateway;
    $gateway->is_active = true;
    $gateway->consumer_key = null;
    $gateway->consumer_secret = null;

    expect($gateway->isConfigured())->toBeFalse();
});

test('isConfigured returns true with active gateway and keys', function () {
    $customer = Customer::factory()->create();
    $gateway = CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'key_abc_123_xyz',
        'consumer_secret' => 'secret_abc_123',
        'is_active' => true,
    ]);

    expect($gateway->fresh()->isConfigured())->toBeTrue();
});

// ── Customer model helpers ────────────────────────────────────────────────────

test('customer paymentGateways relationship works', function () {
    $customer = Customer::factory()->create();
    CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'key_test_123456',
        'consumer_secret' => 'sec_test_123456',
        'is_active' => true,
    ]);

    expect($customer->paymentGateways)->toHaveCount(1);
    expect($customer->clickpesaGateway())->not->toBeNull();
});

test('isClickPesaConfigured returns false without verified gateway', function () {
    $customer = Customer::factory()->create();
    CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'key_test_123456',
        'consumer_secret' => 'sec_test_123456',
        'is_active' => true,
        'verified_at' => null,
    ]);

    expect($customer->isClickPesaConfigured())->toBeFalse();
});

test('isClickPesaConfigured returns true with active verified gateway', function () {
    $customer = Customer::factory()->create();
    CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'key_test_123456',
        'consumer_secret' => 'sec_test_123456',
        'is_active' => true,
        'verified_at' => now(),
    ]);

    expect($customer->isClickPesaConfigured())->toBeTrue();
});

// ── PaymentGatewayService dynamic resolution ──────────────────────────────────

test('forCustomer uses system credentials when no gateway configured', function () {
    $customer = Customer::factory()->create();
    $service = PaymentGatewayService::forCustomer($customer);

    expect($service->isUsingCustomerCredentials())->toBeFalse();
    expect($service->activeGatewayId())->toBeNull();
});

test('forCustomer uses customer credentials when active gateway exists', function () {
    $customer = Customer::factory()->create();
    $gateway = CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'cust_key_abc123',
        'consumer_secret' => 'cust_sec_abc123',
        'is_active' => true,
    ]);

    $service = PaymentGatewayService::forCustomer($customer);

    expect($service->isUsingCustomerCredentials())->toBeTrue();
    expect($service->activeGatewayId())->toBe($gateway->id);
});

test('forCustomer falls back to system when gateway is inactive', function () {
    $customer = Customer::factory()->create();
    CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'cust_key_abc123',
        'consumer_secret' => 'cust_sec_abc123',
        'is_active' => false,
    ]);

    $service = PaymentGatewayService::forCustomer($customer);

    expect($service->isUsingCustomerCredentials())->toBeFalse();
});

test('forRouter resolves customer credentials from router owner', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);
    CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'router_key_abc1',
        'consumer_secret' => 'router_sec_abc1',
        'is_active' => true,
    ]);

    $service = PaymentGatewayService::forRouter($router);

    expect($service->isUsingCustomerCredentials())->toBeTrue();
});

test('forRouter falls back to system for unclaimed router', function () {
    $router = Router::factory()->create(['user_id' => null]);
    $service = PaymentGatewayService::forRouter($router);

    expect($service->isUsingCustomerCredentials())->toBeFalse();
});

test('forHotspotPayment uses customer_payment_gateway_id so verify uses same ClickPesa app as initiate', function () {
    $customer = Customer::factory()->create();
    $gateway = CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'hotspot_key_xyz',
        'consumer_secret' => 'hotspot_sec_xyz',
        'is_active' => true,
    ]);
    $router = Router::factory()->create(['user_id' => $customer->id]);
    $plan = CustomerBillingPlan::factory()->create(['customer_id' => $customer->id]);

    $payment = HotspotPayment::create([
        'router_id' => $router->id,
        'plan_id' => $plan->id,
        'customer_payment_gateway_id' => $gateway->id,
        'client_mac' => 'AA:BB:CC:DD:EE:FF',
        'client_ip' => '192.168.1.1',
        'phone' => '255712345678',
        'amount' => 500,
        'reference' => 'HP-ABCDEFGHIJKL',
        'status' => 'pending',
    ]);

    $service = PaymentGatewayService::forHotspotPayment($payment);

    expect($service->isUsingCustomerCredentials())->toBeTrue()
        ->and($service->activeGatewayId())->toBe($gateway->id);
});

// ── Livewire save credentials ──────────────────────────────────────────────────

test('customer can save ClickPesa credentials via Livewire', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(PaymentSettings::class)
        ->set('consumerKey', 'test_consumer_key_abcdef')
        ->set('consumerSecret', 'test_consumer_secret_xyz')
        ->set('accountNumber', '255712000000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', true);

    $gateway = CustomerPaymentGateway::where('customer_id', $customer->id)->first();
    expect($gateway)->not->toBeNull();
    expect($gateway->consumer_key)->toBe('test_consumer_key_abcdef');
    expect($gateway->account_number)->toBe('255712000000');
    expect($gateway->is_active)->toBeTrue();
    expect($gateway->verified_at)->toBeNull();
});

test('save validates consumer key is required', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(PaymentSettings::class)
        ->set('consumerKey', '')
        ->set('consumerSecret', 'test_consumer_secret_xyz')
        ->call('save')
        ->assertHasErrors(['consumerKey']);
});

// ── Livewire test connection ───────────────────────────────────────────────────

test('testConnection sets testPassed true on valid credentials', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response([
            'success' => true,
            'token' => 'Bearer eyJfaketoken',
        ], 200),
    ]);

    $customer = Customer::factory()->create();
    CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'cust_key_abc123x',
        'consumer_secret' => 'cust_sec_abc123x',
        'is_active' => true,
    ]);

    Livewire::actingAs($customer)
        ->test(PaymentSettings::class)
        ->call('testConnection')
        ->assertSet('testPassed', true);

    expect(CustomerPaymentGateway::where('customer_id', $customer->id)->first()->verified_at)->not->toBeNull();
});

test('testConnection sets testPassed false on API error', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response([
            'message' => 'Invalid credentials',
        ], 401),
    ]);

    $customer = Customer::factory()->create();
    CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'bad_key_abc_1234',
        'consumer_secret' => 'bad_secret_abc_1',
        'is_active' => true,
    ]);

    Livewire::actingAs($customer)
        ->test(PaymentSettings::class)
        ->call('testConnection')
        ->assertSet('testPassed', false);
});

test('testConnection returns error message when no gateway saved', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer)
        ->test(PaymentSettings::class)
        ->call('testConnection')
        ->assertSet('testPassed', false);
});

// ── Disable gateway ───────────────────────────────────────────────────────────

test('customer can disable their gateway', function () {
    $customer = Customer::factory()->create();
    CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'cust_key_abc1234',
        'consumer_secret' => 'cust_sec_abc1234',
        'is_active' => true,
        'verified_at' => now(),
    ]);

    Livewire::actingAs($customer)
        ->test(PaymentSettings::class)
        ->call('disableGateway');

    $gateway = CustomerPaymentGateway::where('customer_id', $customer->id)->first();
    expect($gateway->is_active)->toBeFalse();
    expect($gateway->verified_at)->toBeNull();
});

// ── Admin force-disable ───────────────────────────────────────────────────────

test('admin can force-disable customer gateway', function () {
    $admin = User::factory()->create();
    $customer = Customer::factory()->create();
    $gateway = CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'cust_key_abc1234',
        'consumer_secret' => 'cust_sec_abc1234',
        'is_active' => true,
        'verified_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test('admin.portal-customers')
        ->call('disableGateway', $gateway->id);

    expect($gateway->fresh()->is_active)->toBeFalse();
    expect($gateway->fresh()->verified_at)->toBeNull();
});

// ── Encryption ────────────────────────────────────────────────────────────────

test('credentials are stored encrypted in the database', function () {
    $customer = Customer::factory()->create();
    CustomerPaymentGateway::create([
        'customer_id' => $customer->id,
        'gateway' => 'clickpesa',
        'consumer_key' => 'plaintext_key_abc',
        'consumer_secret' => 'plaintext_sec_xyz',
        'is_active' => true,
    ]);

    $raw = DB::table('customer_payment_gateways')
        ->where('customer_id', $customer->id)
        ->value('consumer_key');

    expect($raw)->not->toBe('plaintext_key_abc');
    expect(strlen($raw))->toBeGreaterThan(30);
});
