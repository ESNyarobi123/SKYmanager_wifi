<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Access control ────────────────────────────────────────────────────────────

test('guest cannot access portal customers page', function () {
    $this->get(route('admin.portal-customers'))->assertRedirect(route('login'));
});

test('admin can access portal customers page', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.portal-customers'))
        ->assertOk();
});

// ── Suspension ────────────────────────────────────────────────────────────────

test('admin can suspend a portal customer', function () {
    $admin = User::factory()->create();
    $customer = Customer::factory()->create(['is_suspended' => false]);

    $this->actingAs($admin)
        ->get(route('admin.portal-customers'));

    Livewire::actingAs($admin)
        ->test('admin.portal-customers')
        ->call('suspend', $customer->id);

    expect($customer->fresh()->is_suspended)->toBeTrue();
});

test('admin can unsuspend a portal customer', function () {
    $admin = User::factory()->create();
    $customer = Customer::factory()->suspended()->create();

    Livewire::actingAs($admin)
        ->test('admin.portal-customers')
        ->call('suspend', $customer->id);

    expect($customer->fresh()->is_suspended)->toBeFalse();
});

// ── Soft delete + restore ──────────────────────────────────────────────────────

test('admin can soft-delete a portal customer', function () {
    $admin = User::factory()->create();
    $customer = Customer::factory()->create();

    Livewire::actingAs($admin)
        ->test('admin.portal-customers')
        ->call('delete', $customer->id);

    expect(Customer::withTrashed()->find($customer->id)->trashed())->toBeTrue();
});

test('admin can restore a soft-deleted customer', function () {
    $admin = User::factory()->create();
    $customer = Customer::factory()->create();
    $customer->delete();

    Livewire::actingAs($admin)
        ->test('admin.portal-customers')
        ->call('restore', $customer->id);

    expect(Customer::find($customer->id))->not->toBeNull();
});

// ── Settings ──────────────────────────────────────────────────────────────────

test('admin can access system settings page', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.system-settings'))
        ->assertOk();
});

test('admin can access activity log page', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.activity-log'))
        ->assertOk();
});
