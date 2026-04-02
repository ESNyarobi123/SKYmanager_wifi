<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUlids, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company_name',
        'referral_code',
        'referred_by',
        'is_suspended',
        'onboarding_completed',
        'portal_subdomain',
        'email_verified_at',
        'phone_verified_at',
        'password',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_suspended' => 'boolean',
            'onboarding_completed' => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function routers(): HasMany
    {
        return $this->hasMany(Router::class, 'user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function paymentGateways(): HasMany
    {
        return $this->hasMany(CustomerPaymentGateway::class, 'customer_id');
    }

    public function billingPlans(): HasMany
    {
        return $this->hasMany(CustomerBillingPlan::class, 'customer_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Generate and persist a unique portal subdomain for this customer.
     * Format: portal-{6 random alphanumeric chars}
     */
    public function generatePortalSubdomain(): string
    {
        do {
            $subdomain = 'portal-'.strtolower(substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 6));
        } while (self::where('portal_subdomain', $subdomain)->exists());

        $this->update(['portal_subdomain' => $subdomain]);

        return $subdomain;
    }

    /**
     * Return the full captive portal URL for this customer.
     * Path-based: {app_url}/p/{subdomain}  (works without wildcard DNS).
     */
    public function portalUrl(): string
    {
        $subdomain = $this->portal_subdomain ?? $this->generatePortalSubdomain();

        return rtrim(config('app.url'), '/').'/p/'.$subdomain;
    }

    public function clickpesaGateway(): ?CustomerPaymentGateway
    {
        return $this->paymentGateways()
            ->where('gateway', 'clickpesa')
            ->first();
    }

    public function isClickPesaConfigured(): bool
    {
        return $this->paymentGateways()
            ->where('gateway', 'clickpesa')
            ->where('is_active', true)
            ->whereNotNull('verified_at')
            ->exists();
    }

    public function isSuspended(): bool
    {
        return (bool) $this->is_suspended;
    }

    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(['admin', 'super-admin']);
    }

    public function isReseller(): bool
    {
        return $this->hasRole('reseller');
    }

    public function unreadNotificationCount(): int
    {
        return $this->unreadNotifications()->count();
    }

    public function totalRevenue(): float
    {
        $routerIds = $this->routers()->pluck('id');
        $subscriptionIds = Subscription::whereIn('router_id', $routerIds)->pluck('id');

        return (float) Payment::whereIn('subscription_id', $subscriptionIds)
            ->where('status', 'success')
            ->sum('amount');
    }

    public function activeRouterCount(): int
    {
        return $this->routers()->where('is_online', true)->count();
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
