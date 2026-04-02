<?php

use App\Livewire\Customer\MyRouters;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Invoice access ────────────────────────────────────────────────────────────

test('customer can view invoices page', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer)
        ->get(route('customer.invoices'))
        ->assertOk();
});

test('customer can only see their own invoices', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();

    Invoice::factory()->create(['customer_id' => $customer->id, 'invoice_number' => 'INV-2026-000001']);
    Invoice::factory()->create(['customer_id' => $other->id, 'invoice_number' => 'INV-2026-000002']);

    $this->actingAs($customer)
        ->get(route('customer.invoices'))
        ->assertOk();

    expect($customer->invoices()->count())->toBe(1);
    expect($other->invoices()->count())->toBe(1);
});

test('customer cannot download another customers invoice', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();

    $invoice = Invoice::factory()->create(['customer_id' => $other->id]);

    $this->actingAs($customer)
        ->get(route('customer.invoices.download', $invoice))
        ->assertForbidden();
});

test('customer can download their own invoice pdf', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($customer)
        ->get(route('customer.invoices.download', $invoice))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('unauthenticated access to invoices redirects to customer login', function () {
    $this->get(route('customer.invoices'))
        ->assertRedirect(route('customer.login'));
});

// ── Invoice model ─────────────────────────────────────────────────────────────

test('invoice generates unique sequential number per year', function () {
    $n1 = Invoice::generateNumber();
    Invoice::factory()->create(['invoice_number' => $n1]);

    $n2 = Invoice::generateNumber();

    expect($n1)->toStartWith('INV-'.now()->year.'-');
    expect($n2)->not->toBe($n1);
    expect($n2)->toEndWith('000002');
});

test('invoice belongs to customer', function () {
    $customer = Customer::factory()->create();
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

    expect($invoice->customer->id)->toBe($customer->id);
});

test('invoice isPaid returns true for paid status', function () {
    $invoice = Invoice::factory()->create(['status' => 'paid']);
    expect($invoice->isPaid())->toBeTrue();

    $invoice2 = Invoice::factory()->create(['status' => 'issued']);
    expect($invoice2->isPaid())->toBeFalse();
});

// ── Notifications ─────────────────────────────────────────────────────────────

test('customer can view notifications page', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer)
        ->get(route('customer.notifications'))
        ->assertOk();
});

// ── Router rename ─────────────────────────────────────────────────────────────

test('customer can rename their own router via livewire', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id, 'name' => 'Old Name']);

    $this->actingAs($customer);

    Livewire::actingAs($customer)
        ->test(MyRouters::class)
        ->call('openRenameModal', $router->id)
        ->set('newName', 'New Name')
        ->call('renameRouter');

    expect($router->fresh()->name)->toBe('New Name');
});
