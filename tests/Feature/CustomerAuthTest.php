<?php

use App\Models\Customer;
use App\Models\Router;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ── Registration ──────────────────────────────────────────────────────────────

test('customer can view register page', function () {
    $this->get(route('customer.register'))
        ->assertOk()
        ->assertSee('Create Your Account');
});

test('customer can register with phone and password', function () {
    $this->post(route('customer.register.store'), [
        'name' => 'Jane Doe',
        'phone' => '255712345678',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('customer.dashboard'));

    $this->assertDatabaseHas('users', ['phone' => '255712345678', 'name' => 'Jane Doe']);
    expect(auth()->check())->toBeTrue();
});

test('customer registration requires unique phone', function () {
    Customer::factory()->create(['phone' => '255712345678']);

    $this->post(route('customer.register.store'), [
        'name' => 'Another Person',
        'phone' => '255712345678',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('phone');
});

// ── Login / Logout ────────────────────────────────────────────────────────────

test('customer can view login page', function () {
    $this->get(route('customer.login'))
        ->assertOk()
        ->assertSee('Sign In');
});

test('customer can login with correct credentials', function () {
    $customer = Customer::factory()->create([
        'phone' => '255799000001',
        'password' => Hash::make('password'),
    ]);

    $this->post(route('customer.login.store'), [
        'phone' => '255799000001',
        'password' => 'password',
    ])->assertRedirect(route('customer.dashboard'));

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($customer->id);
});

test('customer cannot login with wrong password', function () {
    Customer::factory()->create(['phone' => '255799000002', 'password' => Hash::make('correct')]);

    $this->post(route('customer.login.store'), [
        'phone' => '255799000002',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('phone');

    expect(auth()->check())->toBeFalse();
});

test('customer can logout', function () {
    $customer = Customer::factory()->create();
    $this->actingAs($customer);

    $this->post(route('customer.logout'))
        ->assertRedirect(route('customer.login'));

    expect(auth()->check())->toBeFalse();
});

// ── Dashboard access ──────────────────────────────────────────────────────────

test('authenticated customer can access dashboard', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer)
        ->get(route('customer.dashboard'))
        ->assertOk();
});

test('unauthenticated user is redirected from customer dashboard', function () {
    $this->get(route('customer.dashboard'))
        ->assertRedirect(route('customer.login'));
});

test('admin user without customer role cannot access customer dashboard', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('customer.dashboard'))
        ->assertForbidden();
});

// ── Router claiming ───────────────────────────────────────────────────────────

test('customer can view claim router page', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer)
        ->get(route('customer.routers.claim'))
        ->assertOk();
});

test('customer router belongs to customer after claiming', function () {
    $customer = Customer::factory()->create();

    $router = Router::factory()->create(['user_id' => $customer->id]);

    expect($router->user_id)->toBe($customer->id);
    expect($router->isClaimed())->toBeTrue();
    expect($customer->routers)->toHaveCount(1);
});
