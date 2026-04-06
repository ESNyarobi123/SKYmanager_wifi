<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('staff fortify login ignores intended customer portal url', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->withSession(['url.intended' => route('customer.payment-settings')])
        ->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($admin);
});

test('customer fortify login may follow intended url inside customer area', function () {
    $customer = User::factory()->customer()->create([
        'email' => 'portal-customer@example.test',
        'email_verified_at' => now(),
    ]);

    $response = $this->withSession(['url.intended' => route('customer.invoices')])
        ->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'password',
        ]);

    $response->assertRedirect(route('customer.invoices'));
    $this->assertAuthenticatedAs($customer);
});

test('customer visiting staff dashboard is redirected to customer dashboard', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertRedirect(route('customer.dashboard'));
});
