<?php

use App\Models\Customer;
use App\Models\Referral;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── ReferralService ───────────────────────────────────────────────────────────

test('generates a unique referral code', function () {
    $service = app(ReferralService::class);
    $code1 = $service->generateCode();
    $code2 = $service->generateCode();

    expect($code1)->toHaveLength(8);
    expect($code2)->not->toBe($code1);
});

test('applyCode creates a pending referral record', function () {
    $referrer = Customer::factory()->create(['referral_code' => 'ABCD1234']);
    $referred = Customer::factory()->create();

    $service = app(ReferralService::class);
    $referral = $service->applyCode($referred, 'ABCD1234');

    expect($referral)->not->toBeNull();
    expect($referral->referrer_id)->toBe($referrer->id);
    expect($referral->referred_id)->toBe($referred->id);
    expect($referral->status)->toBe('pending');
});

test('applyCode returns null for invalid code', function () {
    $referred = Customer::factory()->create();
    $service = app(ReferralService::class);

    expect($service->applyCode($referred, 'INVALID1'))->toBeNull();
});

test('applyCode prevents self-referral', function () {
    $customer = Customer::factory()->create(['referral_code' => 'SELFREF1']);
    $service = app(ReferralService::class);

    expect($service->applyCode($customer, 'SELFREF1'))->toBeNull();
});

// ── Registration with referral code ──────────────────────────────────────────

test('customer gets referral_code on registration', function () {
    $this->post(route('customer.register.store'), [
        'name' => 'John Doe',
        'phone' => '0712000001',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirect(route('customer.dashboard'));

    $customer = Customer::where('phone', '0712000001')->first();
    expect($customer->referral_code)->not->toBeNull();
    expect($customer->referral_code)->toHaveLength(8);
});

test('registration with valid ref code creates referral', function () {
    $referrer = Customer::factory()->create(['referral_code' => 'REFCODE1']);

    $this->post(route('customer.register.store'), [
        'name' => 'Jane Doe',
        'phone' => '0712000002',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'ref' => 'REFCODE1',
    ])->assertRedirect(route('customer.dashboard'));

    $referred = Customer::where('phone', '0712000002')->first();
    expect($referred->referred_by)->toBe($referrer->id);
    expect(Referral::where('referred_id', $referred->id)->exists())->toBeTrue();
});

// ── Suspension ────────────────────────────────────────────────────────────────

test('suspended customer cannot log in', function () {
    $customer = Customer::factory()->create([
        'password' => bcrypt('Password1!'),
        'is_suspended' => true,
    ]);

    $this->post(route('customer.login.store'), [
        'phone' => $customer->phone,
        'password' => 'Password1!',
    ])->assertRedirect();

    expect(auth()->check())->toBeFalse();
});

// ── Referral page ─────────────────────────────────────────────────────────────

test('customer can view referral page', function () {
    $customer = Customer::factory()->create(['referral_code' => 'MYCODE12']);

    $this->actingAs($customer)
        ->get(route('customer.referral'))
        ->assertOk();
});

// ── Referral model ────────────────────────────────────────────────────────────

test('referral factory creates pending record', function () {
    $referral = Referral::factory()->create();
    expect($referral->status)->toBe('pending');
});

test('referral applied state works', function () {
    $referral = Referral::factory()->applied()->create();
    expect($referral->isApplied())->toBeTrue();
    expect($referral->applied_at)->not->toBeNull();
});
