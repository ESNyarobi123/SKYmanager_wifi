<?php

use App\Services\PaymentGatewayService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::forget(PaymentGatewayService::TOKEN_CACHE_KEY);
    config([
        'services.clickpesa.client_id' => 'test-client-id',
        'services.clickpesa.api_key' => 'test-api-key',
    ]);
});

test('initiatePayment uses official generate-token with client-id and api-key headers', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response([
            'success' => true,
            'token' => 'Bearer eyJfake',
        ], 200),
        'api.clickpesa.com/third-parties/payments/initiate-ussd-push-request' => Http::response([
            'id' => 'txn-abc-123',
            'status' => 'PROCESSING',
            'channel' => 'TIGO-PESA',
            'orderReference' => 'SKY-TEST001',
            'collectedAmount' => null,
            'collectedCurrency' => 'TZS',
            'createdAt' => now()->toIso8601String(),
            'clientId' => 'client-xyz',
        ], 200),
    ]);

    $service = new PaymentGatewayService;
    $result = $service->initiatePayment('0712345678', 1000.0, 'SKY-TEST001');

    expect($result['transactionId'])->toBe('txn-abc-123')
        ->and($result['channel'])->toBe('TIGO-PESA')
        ->and($result['status'])->toBe('pending')
        ->and($result['orderReference'])->toBe('SKY-TEST001');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'generate-token')) {
            return false;
        }

        return $request->header('client-id')[0] === 'test-client-id'
            && $request->header('api-key')[0] === 'test-api-key';
    });
});

test('generate-token falls back to legacy path when official endpoint returns 404', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response(['message' => 'Not found'], 404),
        'api.clickpesa.com/third-parties/non-trade/users/generate-token' => Http::response([
            'token' => 'Bearer eyJlegacy',
        ], 200),
        'api.clickpesa.com/third-parties/payments/initiate-ussd-push-request' => Http::response([
            'id' => 'txn-legacy',
            'status' => 'PROCESSING',
            'channel' => 'M-PESA',
            'orderReference' => 'SKY-LEG',
        ], 200),
    ]);

    $service = new PaymentGatewayService;
    $result = $service->initiatePayment('0712345678', 500.0, 'SKY-LEG');

    expect($result['transactionId'])->toBe('txn-legacy');
});

test('initiatePayment normalizes phone number starting with 0', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response(['success' => true, 'token' => 'Bearer eyJfake'], 200),
        'api.clickpesa.com/third-parties/payments/initiate-ussd-push-request' => Http::response([
            'id' => 'txn-norm',
            'status' => 'PROCESSING',
            'channel' => 'M-PESA',
            'orderReference' => 'SKY-NORM',
        ], 200),
    ]);

    $service = new PaymentGatewayService;
    $service->initiatePayment('0765000000', 500.0, 'SKY-NORM');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'initiate-ussd-push-request')
        && ($r->data()['phoneNumber'] ?? null) === '255765000000');
});

test('verifyTransaction maps PROCESSING status to pending', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response(['success' => true, 'token' => 'Bearer eyJfake'], 200),
        'api.clickpesa.com/third-parties/payments/txn-abc-123' => Http::response([
            'id' => 'txn-abc-123',
            'status' => 'PROCESSING',
            'channel' => 'TIGO-PESA',
            'collectedAmount' => null,
        ], 200),
    ]);

    $service = new PaymentGatewayService;
    $result = $service->verifyTransaction('txn-abc-123');

    expect($result['status'])->toBe('pending')
        ->and($result['channel'])->toBe('TIGO-PESA');
});

test('verifyTransaction maps SUCCESS status to success', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response(['success' => true, 'token' => 'Bearer eyJfake'], 200),
        'api.clickpesa.com/third-parties/payments/txn-success' => Http::response([
            'id' => 'txn-success',
            'status' => 'SUCCESS',
            'channel' => 'M-PESA',
            'collectedAmount' => '1000',
        ], 200),
    ]);

    $service = new PaymentGatewayService;
    $result = $service->verifyTransaction('txn-success');

    expect($result['status'])->toBe('success')
        ->and($result['amount'])->toBe(1000.0);
});

test('verifyTransaction maps SETTLED status to success', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response(['success' => true, 'token' => 'Bearer eyJfake'], 200),
        'api.clickpesa.com/third-parties/payments/txn-settled' => Http::response([
            'id' => 'txn-settled',
            'status' => 'SETTLED',
            'channel' => 'AIRTEL-MONEY',
            'collectedAmount' => '2000',
        ], 200),
    ]);

    $service = new PaymentGatewayService;
    $result = $service->verifyTransaction('txn-settled');

    expect($result['status'])->toBe('success');
});

test('verifyTransaction maps FAILED status to failed', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response(['success' => true, 'token' => 'Bearer eyJfake'], 200),
        'api.clickpesa.com/third-parties/payments/txn-fail' => Http::response([
            'id' => 'txn-fail',
            'status' => 'FAILED',
            'channel' => 'TIGO-PESA',
            'collectedAmount' => null,
        ], 200),
    ]);

    $service = new PaymentGatewayService;
    $result = $service->verifyTransaction('txn-fail');

    expect($result['status'])->toBe('failed');
});

test('getAccessToken is cached and reused across calls', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response(['success' => true, 'token' => 'Bearer eyJcached'], 200),
        'api.clickpesa.com/third-parties/payments/initiate-ussd-push-request' => Http::response([
            'id' => 'txn-cache-1', 'status' => 'PROCESSING', 'channel' => 'M-PESA', 'orderReference' => 'REF-1',
        ], 200),
        'api.clickpesa.com/third-parties/payments/*' => Http::response([
            'id' => 'txn-cache-1', 'status' => 'PROCESSING', 'channel' => 'M-PESA', 'collectedAmount' => null,
        ], 200),
    ]);

    $service = new PaymentGatewayService;
    $service->initiatePayment('0712345678', 500.0, 'REF-1');
    $service->verifyTransaction('txn-cache-1');

    Http::assertSentCount(3);
});

test('initiatePayment throws exception on API error', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response(['success' => true, 'token' => 'Bearer eyJfake'], 200),
        'api.clickpesa.com/third-parties/payments/initiate-ussd-push-request' => Http::response([
            'message' => 'Invalid phone number',
        ], 422),
    ]);

    $service = new PaymentGatewayService;

    expect(fn () => $service->initiatePayment('0712345678', 500.0, 'SKY-ERR'))
        ->toThrow(Exception::class, 'Payment initiation failed');
});

test('getAccessToken throws exception when token field missing', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response(['success' => true, 'other' => 'data'], 200),
        'api.clickpesa.com/third-parties/non-trade/users/generate-token' => Http::response(['other' => 'data'], 200),
    ]);

    $service = new PaymentGatewayService;

    expect(fn () => $service->initiatePayment('0712345678', 500.0, 'SKY-X'))
        ->toThrow(Exception::class);
});

test('token without Bearer prefix is normalized for API calls', function () {
    Http::fake([
        'api.clickpesa.com/third-parties/generate-token' => Http::response([
            'success' => true,
            'token' => 'eyJraw-only',
        ], 200),
        'api.clickpesa.com/third-parties/payments/initiate-ussd-push-request' => Http::response([
            'id' => 'txn-b', 'status' => 'PROCESSING', 'channel' => 'M-PESA', 'orderReference' => 'R1',
        ], 200),
    ]);

    $service = new PaymentGatewayService;
    $service->initiatePayment('0712345678', 100.0, 'R1');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), 'initiate-ussd-push-request')) {
            return false;
        }
        $auth = $request->header('Authorization')[0] ?? '';

        return str_starts_with($auth, 'Bearer ');
    });
});
