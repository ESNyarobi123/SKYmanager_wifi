<?php

use App\Models\BillingPlan;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('generateBatch creates correct number of vouchers', function () {
    $plan = BillingPlan::factory()->create();

    $vouchers = Voucher::generateBatch($plan->id, 5, 'TestBatch');

    expect($vouchers)->toHaveCount(5)
        ->and(Voucher::where('batch_name', 'TestBatch')->count())->toBe(5);
});

test('generated vouchers have unique codes', function () {
    $plan = BillingPlan::factory()->create();

    Voucher::generateBatch($plan->id, 20, 'UniqueBatch');

    $codes = Voucher::where('batch_name', 'UniqueBatch')->pluck('code');
    expect($codes->unique()->count())->toBe(20);
});

test('generateBatch applies prefix to codes', function () {
    $plan = BillingPlan::factory()->create();

    Voucher::generateBatch($plan->id, 3, 'PrefixBatch', 'SKY');

    Voucher::where('batch_name', 'PrefixBatch')->each(function ($v) {
        expect($v->code)->toStartWith('SKY-');
    });
});

test('voucher redeem marks as used with mac address', function () {
    $plan = BillingPlan::factory()->create();
    $voucher = Voucher::factory()->create(['plan_id' => $plan->id, 'status' => 'unused']);

    $redeemed = Voucher::redeem($voucher->code, 'AA:BB:CC:DD:EE:FF');

    expect($redeemed->status)->toBe('used')
        ->and($redeemed->used_by_mac)->toBe('AA:BB:CC:DD:EE:FF')
        ->and($redeemed->used_at)->not->toBeNull();
});

test('redeeming an already used voucher throws exception', function () {
    $plan = BillingPlan::factory()->create();
    $voucher = Voucher::factory()->create(['plan_id' => $plan->id, 'status' => 'used']);

    expect(fn () => Voucher::redeem($voucher->code, 'AA:BB:CC:DD:EE:FF'))
        ->toThrow(Exception::class, 'already been used');
});

test('redeeming an expired voucher throws exception', function () {
    $plan = BillingPlan::factory()->create();
    $voucher = Voucher::factory()->create([
        'plan_id' => $plan->id,
        'status' => 'unused',
        'expires_at' => now()->subDay(),
    ]);

    expect(fn () => Voucher::redeem($voucher->code, 'AA:BB:CC:DD:EE:FF'))
        ->toThrow(Exception::class, 'expired');
});

test('redeeming a non-existent code throws exception', function () {
    expect(fn () => Voucher::redeem('INVALID999', 'AA:BB:CC:DD:EE:FF'))
        ->toThrow(ModelNotFoundException::class);
});

test('voucher belongs to billing plan', function () {
    $plan = BillingPlan::factory()->create();
    $voucher = Voucher::factory()->create(['plan_id' => $plan->id]);

    expect($voucher->plan->id)->toBe($plan->id);
});

test('isExpired returns true when expires_at is in the past', function () {
    $plan = BillingPlan::factory()->create();
    $voucher = Voucher::factory()->create([
        'plan_id' => $plan->id,
        'expires_at' => now()->subHour(),
    ]);

    expect($voucher->isExpired())->toBeTrue();
});

test('isExpired returns false when expires_at is null', function () {
    $plan = BillingPlan::factory()->create();
    $voucher = Voucher::factory()->create(['plan_id' => $plan->id, 'expires_at' => null]);

    expect($voucher->isExpired())->toBeFalse();
});
