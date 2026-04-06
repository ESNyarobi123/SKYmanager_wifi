<?php

use App\Models\CustomerBillingPlan;

test('speed label is unlimited when both speeds are empty', function () {
    $plan = new CustomerBillingPlan([
        'upload_speed_kbps' => null,
        'download_speed_kbps' => null,
    ]);

    expect($plan->speedLabel())->toBe('Unlimited');
});

test('speed label shows kbps style when under one megabit', function () {
    $plan = new CustomerBillingPlan([
        'upload_speed_kbps' => 10,
        'download_speed_kbps' => 512,
    ]);

    expect($plan->speedLabel())->toBe('↑10k / ↓512k');
});

test('speed label shows megabits when at or above one megabit', function () {
    $plan = new CustomerBillingPlan([
        'upload_speed_kbps' => 1024,
        'download_speed_kbps' => 5120,
    ]);

    expect($plan->speedLabel())->toBe('↑1M / ↓5M');
});

test('formatKbpsForLabel returns infinity symbol for zero', function () {
    expect(CustomerBillingPlan::formatKbpsForLabel(null))->toBe('∞');
    expect(CustomerBillingPlan::formatKbpsForLabel(0))->toBe('∞');
});

test('speed label allows one direction unlimited', function () {
    $plan = new CustomerBillingPlan([
        'upload_speed_kbps' => null,
        'download_speed_kbps' => 2048,
    ]);

    expect($plan->speedLabel())->toBe('↑∞ / ↓2M');
});
