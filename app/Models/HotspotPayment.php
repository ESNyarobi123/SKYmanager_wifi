<?php

namespace App\Models;

use App\Services\HotspotPaymentAuthorizationService;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class HotspotPayment extends Model
{
    use HasUlids;

    protected $fillable = [
        'router_id',
        'plan_id',
        'customer_payment_gateway_id',
        'client_mac',
        'client_ip',
        'phone',
        'amount',
        'reference',
        'transaction_id',
        'status',
        'authorized_at',
        'expires_at',
        'provider_confirmed_at',
        'authorize_attempts',
        'last_authorize_error',
        'authorization_job_dispatched_at',
        'first_authorize_failure_at',
        'last_authorize_failed_at',
        'last_authorize_error_code',
        'last_authorize_health_snapshot',
        'last_authorize_attempt_context',
        'last_failure_router_online',
        'last_failure_overall_health',
        'last_failure_tunnel_level',
        'last_failure_api_level',
        'last_failure_portal_level',
        'router_ready_for_authorize_at_failure',
        'provider_confirmed_at_failure',
        'authorize_retry_exhausted_at',
        'recovered_after_failure_at',
        'failed_authorize_attempts_before_success',
        'seconds_to_recover_from_first_failure',
        'admin_authorize_retry_count',
        'last_admin_authorize_retry_at',
        'router_bytes_in',
        'router_bytes_out',
        'router_usage_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'authorized_at' => 'datetime',
            'expires_at' => 'datetime',
            'router_bytes_in' => 'integer',
            'router_bytes_out' => 'integer',
            'router_usage_synced_at' => 'datetime',
            'provider_confirmed_at' => 'datetime',
            'authorization_job_dispatched_at' => 'datetime',
            'authorize_attempts' => 'integer',
            'first_authorize_failure_at' => 'datetime',
            'last_authorize_failed_at' => 'datetime',
            'last_authorize_health_snapshot' => 'array',
            'last_authorize_attempt_context' => 'array',
            'last_failure_router_online' => 'boolean',
            'router_ready_for_authorize_at_failure' => 'boolean',
            'provider_confirmed_at_failure' => 'boolean',
            'authorize_retry_exhausted_at' => 'datetime',
            'recovered_after_failure_at' => 'datetime',
            'failed_authorize_attempts_before_success' => 'integer',
            'seconds_to_recover_from_first_failure' => 'integer',
            'admin_authorize_retry_count' => 'integer',
            'last_admin_authorize_retry_at' => 'datetime',
        ];
    }

    /**
     * Idempotently mark a pending payment as provider-confirmed and queue router authorization.
     * Duplicate callbacks and concurrent polls converge on a single success transition.
     */
    public static function markProviderConfirmedByReference(string $reference, ?string $transactionId = null): ?static
    {
        return DB::transaction(function () use ($reference, $transactionId) {
            /** @var static|null $locked */
            $locked = static::where('reference', $reference)->lockForUpdate()->first();

            if (! $locked) {
                return null;
            }

            if (in_array($locked->status, ['authorized', 'failed'], true)) {
                return $locked;
            }

            if ($locked->status === 'success') {
                return $locked;
            }

            if ($locked->status !== 'pending') {
                return $locked;
            }

            $locked->update([
                'status' => 'success',
                'provider_confirmed_at' => now(),
                'transaction_id' => $transactionId ?? $locked->transaction_id,
            ]);

            app(HotspotPaymentAuthorizationService::class)->dispatchAuthorization($locked->fresh());

            return $locked->fresh();
        });
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CustomerBillingPlan::class, 'plan_id');
    }

    public function customerPaymentGateway(): BelongsTo
    {
        return $this->belongsTo(CustomerPaymentGateway::class, 'customer_payment_gateway_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAuthorized(): bool
    {
        return $this->status === 'authorized';
    }
}
