<?php

use App\Models\BillingPlan;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('billing plan can be created with factory', function () {
    $plan = BillingPlan::factory()->create();

    expect($plan->id)->toBeString()
        ->and($plan->price)->toBeNumeric()
        ->and($plan->duration_minutes)->toBeInt()
        ->and($plan->is_active)->toBeTrue();
});

test('billing plan ulid is 26 chars', function () {
    $plan = BillingPlan::factory()->create();

    expect(strlen($plan->id))->toBe(26);
});

test('billing plan inactive state works', function () {
    $plan = BillingPlan::factory()->inactive()->create();

    expect($plan->is_active)->toBeFalse();
});

test('billing plan has many subscriptions relationship', function () {
    $plan = BillingPlan::factory()->create();

    expect($plan->subscriptions())->toBeInstanceOf(HasMany::class);
});
