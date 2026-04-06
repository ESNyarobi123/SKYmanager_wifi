<?php

namespace App\Services;

use App\Models\CustomerPaymentGateway;
use App\Models\HotspotPayment;
use App\Models\Router;
use App\Models\User;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    private const BASE_URL = 'https://api.clickpesa.com/third-parties';

    private const TOKEN_TTL_SECONDS = 3300;

    /**
     * System-level token cache key (backward-compatible with previous single key).
     */
    public const TOKEN_CACHE_KEY = 'clickpesa_access_token';

    private string $clientId = '';

    private string $apiKey = '';

    private string $tokenCacheKey;

    private bool $usingCustomerCredentials = false;

    private ?string $gatewayId = null;

    public function __construct()
    {
        $this->clientId = (string) config('services.clickpesa.client_id', '');
        $this->apiKey = (string) config('services.clickpesa.api_key', '');
        $this->tokenCacheKey = self::TOKEN_CACHE_KEY;
    }

    /**
     * Return a new service instance configured with a specific customer's ClickPesa
     * credentials. Falls back to system credentials when the customer has
     * no active, configured gateway.
     *
     * Each customer has at most one ClickPesa row (unique customer_id + gateway);
     * we still order deterministically for safety.
     */
    public static function forCustomer(User $customer): static
    {
        $instance = new static;

        $gateway = $customer->paymentGateways()
            ->where('gateway', 'clickpesa')
            ->where('is_active', true)
            ->orderByDesc('verified_at')
            ->orderByDesc('last_used_at')
            ->orderByDesc('updated_at')
            ->first();

        if ($gateway instanceof CustomerPaymentGateway && $gateway->isConfigured()) {
            static::hydrateFromGateway($instance, $gateway);
        }

        return $instance;
    }

    /**
     * Resolve API credentials from the router owner's gateway (same as forCustomer).
     */
    public static function forRouter(Router $router): static
    {
        if ($router->user_id) {
            $router->loadMissing('user');

            if ($router->user instanceof User) {
                return static::forCustomer($router->user);
            }
        }

        return new static;
    }

    /**
     * Use the same ClickPesa application that initiated a hotspot payment so
     * verify/callback polling never mixes tokens between customers or between
     * system vs merchant credentials.
     */
    public static function forHotspotPayment(HotspotPayment $payment): static
    {
        $payment->loadMissing(['router', 'router.user']);
        $router = $payment->router;

        if (! $router || ! $router->user_id) {
            return new static;
        }

        if ($payment->customer_payment_gateway_id) {
            $gateway = CustomerPaymentGateway::find($payment->customer_payment_gateway_id);

            if (
                $gateway instanceof CustomerPaymentGateway
                && $gateway->customer_id === $router->user_id
                && filled($gateway->consumer_key)
                && filled($gateway->consumer_secret)
            ) {
                return static::forGatewayRecord($gateway, touchLastUsed: false);
            }
        }

        return static::forRouter($router);
    }

    /**
     * Build a service instance for a stored gateway row (used for hotspot reconciliation).
     *
     * @param  bool  $touchLastUsed  When false, skips updating last_used_at (e.g. background verify).
     */
    public static function forGatewayRecord(CustomerPaymentGateway $gateway, bool $touchLastUsed = true): static
    {
        $instance = new static;

        if (! filled($gateway->consumer_key) || ! filled($gateway->consumer_secret)) {
            return $instance;
        }

        static::hydrateFromGateway($instance, $gateway, $touchLastUsed);

        return $instance;
    }

    private static function hydrateFromGateway(self $instance, CustomerPaymentGateway $gateway, bool $touchLastUsed = true): void
    {
        $instance->clientId = (string) $gateway->consumer_key;
        $instance->apiKey = (string) $gateway->consumer_secret;
        $instance->tokenCacheKey = 'clickpesa_token_'.$gateway->id;
        $instance->usingCustomerCredentials = true;
        $instance->gatewayId = $gateway->id;

        if ($touchLastUsed) {
            $gateway->timestamps = false;
            $gateway->update(['last_used_at' => now()]);
            $gateway->timestamps = true;
        }
    }

    public function isUsingCustomerCredentials(): bool
    {
        return $this->usingCustomerCredentials;
    }

    public function activeGatewayId(): ?string
    {
        return $this->gatewayId;
    }

    /**
     * Verify raw credentials against ClickPesa (no long-lived cache). Used from dashboard "Test connection".
     *
     * @return array{ok: bool, message: string|null}
     */
    public static function probeCredentials(string $clientId, string $apiKey): array
    {
        if ($clientId === '' || $apiKey === '') {
            return ['ok' => false, 'message' => 'Client ID and API key are required.'];
        }

        $probe = new self;
        $probe->clientId = $clientId;
        $probe->apiKey = $apiKey;

        try {
            $probe->fetchAccessTokenUncached();

            return ['ok' => true, 'message' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Preview a USSD push to check available payment channels before initiating.
     *
     * @return array{activeMethods: array<int, array{name: string, status: string, fee: int}>, sender: array|null}
     *
     * @throws Exception
     */
    public function previewPayment(string $phone, float $amount, string $orderReference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->getAccessToken(),
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->post(self::BASE_URL.'/payments/preview-ussd-push-request', [
                    'amount' => (string) intval($amount),
                    'currency' => 'TZS',
                    'orderReference' => $orderReference,
                    'phoneNumber' => $this->normalizePhone($phone),
                    'fetchSenderDetails' => true,
                ]);

            $response->throw();

            return $response->json();
        } catch (RequestException $e) {
            Log::warning('ClickPesa preview failed', [
                'phone' => $phone,
                'gateway_id' => $this->gatewayId,
                'customer_credentials' => $this->usingCustomerCredentials,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Payment preview failed: '.$this->extractError($e));
        }
    }

    /**
     * Initiate a USSD-PUSH payment request via ClickPesa.
     *
     * @return array{transactionId: string, orderReference: string, status: string, channel: string}
     *
     * @throws Exception
     */
    public function initiatePayment(string $phone, float $amount, string $orderReference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->getAccessToken(),
                'Accept' => 'application/json',
            ])
                ->timeout(30)
                ->post(self::BASE_URL.'/payments/initiate-ussd-push-request', [
                    'amount' => (string) intval($amount),
                    'currency' => 'TZS',
                    'orderReference' => $orderReference,
                    'phoneNumber' => $this->normalizePhone($phone),
                ]);

            $response->throw();
            $data = $response->json();

            $transactionId = $data['id'] ?? $data['transactionId'] ?? $data['transaction_id'] ?? null;

            if (! is_string($transactionId) || $transactionId === '') {
                throw new Exception('ClickPesa response missing transaction id.');
            }

            Log::info('ClickPesa USSD initiated', [
                'order_reference' => $orderReference,
                'gateway_id' => $this->gatewayId,
                'customer_credentials' => $this->usingCustomerCredentials,
                'channel' => $data['channel'] ?? null,
            ]);

            return [
                'transactionId' => $transactionId,
                'orderReference' => $data['orderReference'] ?? $orderReference,
                'status' => 'pending',
                'channel' => $data['channel'] ?? 'UNKNOWN',
            ];
        } catch (RequestException $e) {
            Log::error('ClickPesa initiate failed', [
                'phone' => $phone,
                'amount' => $amount,
                'gateway_id' => $this->gatewayId,
                'customer_credentials' => $this->usingCustomerCredentials,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Payment initiation failed: '.$this->extractError($e));
        }
    }

    /**
     * Check the status of a ClickPesa transaction by its transaction ID.
     *
     * @return array{status: string, amount: float|null, channel: string|null, message: string}
     *
     * @throws Exception
     */
    public function verifyTransaction(string $transactionId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->getAccessToken(),
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->get(self::BASE_URL.'/payments/'.$transactionId);

            $response->throw();
            $data = $response->json();

            $clickpesaStatus = strtoupper((string) ($data['status'] ?? 'PROCESSING'));

            $status = match ($clickpesaStatus) {
                'SUCCESS', 'SETTLED' => 'success',
                'FAILED' => 'failed',
                default => 'pending',
            };

            return [
                'status' => $status,
                'amount' => isset($data['collectedAmount']) ? (float) $data['collectedAmount'] : null,
                'channel' => $data['channel'] ?? null,
                'message' => $clickpesaStatus,
            ];
        } catch (RequestException $e) {
            Log::error('ClickPesa verify failed', [
                'transactionId' => $transactionId,
                'gateway_id' => $this->gatewayId,
                'customer_credentials' => $this->usingCustomerCredentials,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Payment verification failed: '.$this->extractError($e));
        }
    }

    /**
     * Obtain and cache a ClickPesa JWT access token for this credential set.
     * Per ClickPesa docs, the token value includes the "Bearer " prefix.
     *
     * @throws Exception
     */
    private function getAccessToken(): string
    {
        if ($this->clientId === '' || $this->apiKey === '') {
            throw new Exception('ClickPesa client ID and API key are not configured.');
        }

        return Cache::remember($this->tokenCacheKey, self::TOKEN_TTL_SECONDS, function () {
            return $this->fetchAccessTokenUncached();
        });
    }

    /**
     * Official path: POST /generate-token with headers client-id and api-key.
     * Legacy fallback: POST /non-trade/users/generate-token with JSON body (older integrations).
     *
     * @throws Exception
     */
    private function fetchAccessTokenUncached(): string
    {
        $response = Http::timeout(15)
            ->acceptJson()
            ->withHeaders([
                'client-id' => $this->clientId,
                'api-key' => $this->apiKey,
            ])
            ->post(self::BASE_URL.'/generate-token');

        if ($response->successful()) {
            $token = $response->json('token');
            if (is_string($token) && $token !== '') {
                Log::debug('ClickPesa token obtained via /generate-token', [
                    'gateway_id' => $this->gatewayId,
                ]);

                return $this->normalizeAuthorizationHeaderValue($token);
            }
        }

        if ($response->status() !== 404) {
            $this->throwTokenFailure($response);
        }

        $legacy = Http::timeout(15)
            ->acceptJson()
            ->post(self::BASE_URL.'/non-trade/users/generate-token', [
                'clientId' => $this->clientId,
                'apiKey' => $this->apiKey,
            ]);

        if (! $legacy->successful()) {
            $this->throwTokenFailure($legacy);
        }

        $token = $legacy->json('token');
        if (! is_string($token) || $token === '') {
            throw new Exception('ClickPesa token response missing "token" field.');
        }

        Log::debug('ClickPesa token obtained via legacy /non-trade/users/generate-token', [
            'gateway_id' => $this->gatewayId,
        ]);

        return $this->normalizeAuthorizationHeaderValue($token);
    }

    private function throwTokenFailure(Response $response): void
    {
        $body = $response->json();
        $message = is_array($body)
            ? ($body['message'] ?? $body['error'] ?? $response->body())
            : $response->body();

        throw new Exception('Failed to obtain ClickPesa access token: '.$message);
    }

    /**
     * ClickPesa returns the full Authorization header value including "Bearer ".
     */
    private function normalizeAuthorizationHeaderValue(string $token): string
    {
        $t = trim($token);
        if ($t === '') {
            throw new Exception('ClickPesa returned an empty token.');
        }

        if (preg_match('/^Bearer\s+/i', $t)) {
            return $t;
        }

        return 'Bearer '.$t;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        }

        if (str_starts_with($digits, '255')) {
            return $digits;
        }

        if (strlen($digits) === 9) {
            return '255'.$digits;
        }

        return $digits;
    }

    private function extractError(RequestException $e): string
    {
        $body = $e->response?->json();

        if (! is_array($body)) {
            return $e->getMessage();
        }

        if (isset($body['message']) && is_string($body['message'])) {
            return $body['message'];
        }

        if (isset($body['error']) && is_string($body['error'])) {
            return $body['error'];
        }

        if (isset($body['errors']) && is_array($body['errors'])) {
            $flat = collect($body['errors'])->flatten()->filter()->values()->first();

            if (is_string($flat) && $flat !== '') {
                return $flat;
            }
        }

        return $e->getMessage();
    }
}
