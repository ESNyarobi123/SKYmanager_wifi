<?php

use App\Livewire\Customer\MyRouters;
use App\Models\Customer;
use App\Models\Router;
use App\Models\RouterHotspotActiveSession;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('customer can remove their own router via livewire', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);

    $this->actingAs($customer);

    Livewire::actingAs($customer)
        ->test(MyRouters::class)
        ->call('openDeleteModal', $router->id)
        ->call('deleteRouter');

    expect(Router::find($router->id))->toBeNull();
    expect(Router::withTrashed()->find($router->id)?->trashed())->toBeTrue();
});

test('removing a router expires its subscriptions and deletes hotspot active sessions', function () {
    $customer = Customer::factory()->create();
    $router = Router::factory()->create(['user_id' => $customer->id]);
    $subscription = Subscription::factory()->active()->create(['router_id' => $router->id]);

    RouterHotspotActiveSession::query()->create([
        'router_id' => $router->id,
        'mikrotik_internal_id' => 'sess-1',
        'mac_address' => 'aa:bb:cc:dd:ee:01',
        'synced_at' => now(),
    ]);

    $this->actingAs($customer);

    Livewire::actingAs($customer)
        ->test(MyRouters::class)
        ->call('openDeleteModal', $router->id)
        ->call('deleteRouter');

    expect($subscription->fresh()->status)->toBe('expired');
    expect(RouterHotspotActiveSession::query()->where('router_id', $router->id)->count())->toBe(0);
});

test('customer cannot remove another users router', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();
    $otherRouter = Router::factory()->create(['user_id' => $other->id]);

    $this->actingAs($customer);

    expect(fn () => Livewire::actingAs($customer)
        ->test(MyRouters::class)
        ->set('selectedRouterId', $otherRouter->id)
        ->set('showDeleteModal', true)
        ->call('deleteRouter')
    )->toThrow(ModelNotFoundException::class);
});

test('open delete modal ignores routers not owned by the customer', function () {
    $customer = Customer::factory()->create();
    $other = Customer::factory()->create();
    $otherRouter = Router::factory()->create(['user_id' => $other->id]);

    $this->actingAs($customer);

    Livewire::actingAs($customer)
        ->test(MyRouters::class)
        ->call('openDeleteModal', $otherRouter->id)
        ->assertSet('showDeleteModal', false)
        ->assertSet('selectedRouterId', null);
});
