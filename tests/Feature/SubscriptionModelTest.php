<?php

use App\Models\BillingPlan;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\WifiUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('subscription can be created with factory', function () {
    $plan = BillingPlan::factory()->create();
    $router = Router::factory()->create();
    $user = WifiUser::factory()->create();

    $sub = Subscription::factory()->create([
        'wifi_user_id' => $user->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
    ]);

    expect($sub->id)->toBeString()
        ->and(strlen($sub->id))->toBe(26)
        ->and($sub->wifi_user_id)->toBe($user->id)
        ->and($sub->plan_id)->toBe($plan->id);
});

test('subscription active state sets correct fields', function () {
    $plan = BillingPlan::factory()->create();
    $router = Router::factory()->create();
    $user = WifiUser::factory()->create();

    $sub = Subscription::factory()->active()->create([
        'wifi_user_id' => $user->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
    ]);

    expect($sub->status)->toBe('active')
        ->and($sub->expires_at->isFuture())->toBeTrue();
});

test('subscription isExpired returns true for past expiry', function () {
    $plan = BillingPlan::factory()->create();
    $router = Router::factory()->create();
    $user = WifiUser::factory()->create();

    $sub = Subscription::factory()->expired()->create([
        'wifi_user_id' => $user->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
    ]);

    expect($sub->isExpired())->toBeTrue();
});

test('subscription belongs to wifi user, plan, and router', function () {
    $plan = BillingPlan::factory()->create();
    $router = Router::factory()->create();
    $user = WifiUser::factory()->create();

    $sub = Subscription::factory()->create([
        'wifi_user_id' => $user->id,
        'plan_id' => $plan->id,
        'router_id' => $router->id,
    ]);

    expect($sub->wifiUser->id)->toBe($user->id)
        ->and($sub->plan->id)->toBe($plan->id)
        ->and($sub->router->id)->toBe($router->id);
});
