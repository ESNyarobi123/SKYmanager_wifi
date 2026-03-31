<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    private const BASE_URL = 'https://api.clickpesa.com/third-parties';

    private const TOKEN_CACHE_KEY = 'clickpesa_access_token';

    private const TOKEN_TTL_SECONDS = 3300;

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
            $response = Http::withHeaders(['Authorization' => $this->getAccessToken()])
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
            Log::warning('ClickPesa preview failed', ['phone' => $phone, 'error' => $e->getMessage()]);
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
            $response = Http::withHeaders(['Authorization' => $this->getAccessToken()])
                ->timeout(30)
                ->post(self::BASE_URL.'/payments/initiate-ussd-push-request', [
                    'amount' => (string) intval($amount),
                    'currency' => 'TZS',
                    'orderReference' => $orderReference,
                    'phoneNumber' => $this->normalizePhone($phone),
                ]);

            $response->throw();
            $data = $response->json();

            return [
                'transactionId' => $data['id'],
                'orderReference' => $data['orderReference'] ?? $orderReference,
                'status' => 'pending',
                'channel' => $data['channel'] ?? 'UNKNOWN',
            ];
        } catch (RequestException $e) {
            Log::error('ClickPesa initiate failed', ['phone' => $phone, 'amount' => $amount, 'error' => $e->getMessage()]);
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
            $response = Http::withHeaders(['Authorization' => $this->getAccessToken()])
                ->timeout(15)
                ->get(self::BASE_URL.'/payments/'.$transactionId);

            $response->throw();
            $data = $response->json();

            $clickpesaStatus = strtoupper($data['status'] ?? 'PROCESSING');

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
            Log::error('ClickPesa verify failed', ['transactionId' => $transactionId, 'error' => $e->getMessage()]);
            throw new Exception('Payment verification failed: '.$this->extractError($e));
        }
    }

    /**
     * Obtain and cache a ClickPesa JWT access token.
     * The returned token string already contains the "Bearer " prefix.
     *
     * @throws Exception
     */
    private function getAccessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_TTL_SECONDS, function () {
            $response = Http::timeout(10)
                ->post(self::BASE_URL.'/non-trade/users/generate-token', [
                    'clientId' => config('services.clickpesa.client_id'),
                    'apiKey' => config('services.clickpesa.api_key'),
                ]);

            if (! $response->successful()) {
                throw new Exception('Failed to obtain ClickPesa access token: '.$response->body());
            }

            $token = $response->json('token');

            if (! $token) {
                throw new Exception('ClickPesa token response missing "token" field.');
            }

            return $token;
        });
    }

    /**
     * Normalize a Tanzanian phone number to 255XXXXXXXXX format.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Extract a readable error message from a RequestException.
     */
    private function extractError(RequestException $e): string
    {
        $body = $e->response?->json();

        if (is_array($body)) {
            return $body['message'] ?? $body['error'] ?? $e->getMessage();
        }

        return $e->getMessage();
    }
}
