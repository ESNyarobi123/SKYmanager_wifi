<?php

use App\Models\Router;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('router can be created with factory', function () {
    $router = Router::factory()->create();

    expect($router->id)->toBeString()
        ->and($router->api_port)->toBe(8728)
        ->and($router->is_online)->toBeBool();
});

test('router ulid is automatically generated', function () {
    $router = Router::factory()->create();

    expect(strlen($router->id))->toBe(26);
});

test('router online state works', function () {
    $router = Router::factory()->online()->create();

    expect($router->is_online)->toBeTrue()
        ->and($router->last_seen)->not->toBeNull();
});

test('router offline state works', function () {
    $router = Router::factory()->offline()->create();

    expect($router->is_online)->toBeFalse();
});

test('router has many subscriptions', function () {
    $router = Router::factory()->create();

    expect($router->subscriptions())->toBeInstanceOf(HasMany::class);
});
