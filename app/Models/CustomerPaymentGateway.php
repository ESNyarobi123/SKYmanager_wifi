<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaymentGateway extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'customer_id',
        'gateway',
        'consumer_key',
        'consumer_secret',
        'account_number',
        'is_active',
        'verified_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'consumer_key' => 'encrypted',
            'consumer_secret' => 'encrypted',
            'account_number' => 'encrypted',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Whether the gateway credentials have been verified via a live test.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Whether the gateway is ready to process payments.
     */
    public function isConfigured(): bool
    {
        return $this->is_active
            && filled($this->consumer_key)
            && filled($this->consumer_secret);
    }

    /**
     * Masked consumer key — shows only the last 6 characters.
     * Safe to display in the UI after saving.
     */
    public function maskedConsumerKey(): string
    {
        if (blank($this->consumer_key)) {
            return '';
        }

        return '••••••••'.substr($this->consumer_key, -6);
    }

    /**
     * Masked consumer secret — shows only the last 6 characters.
     */
    public function maskedConsumerSecret(): string
    {
        if (blank($this->consumer_secret)) {
            return '';
        }

        return '••••••••'.substr($this->consumer_secret, -6);
    }

    /**
     * Masked account number — shows only the last 6 characters.
     */
    public function maskedAccountNumber(): string
    {
        if (blank($this->account_number)) {
            return '';
        }

        return '••••'.substr($this->account_number, -6);
    }
}
