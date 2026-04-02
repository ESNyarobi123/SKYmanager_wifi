<?php

use App\Livewire\WelcomePage;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest sees welcome page at root', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeLivewire(WelcomePage::class);
});

test('welcome page contains hero headline', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('on Autopilot');
});

test('welcome page contains customer register link', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('customer.register'));
});

test('welcome page contains admin login link', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('login'));
});

test('authenticated customer is redirected to dashboard from home', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer)
        ->get(route('home'))
        ->assertRedirect(route('customer.dashboard'));
});

test('authenticated admin is redirected to dashboard from home', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
