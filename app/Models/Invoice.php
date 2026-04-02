<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Customer = User with role 'customer' (unified model)

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'payment_id',
        'subscription_id',
        'subtotal',
        'tax_amount',
        'total',
        'currency',
        'status',
        'notes',
        'issued_at',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Human-readable invoice number like INV-2024-000042.
     */
    public static function generateNumber(): string
    {
        $year = now()->year;
        $last = static::whereYear('created_at', $year)->max('invoice_number');

        $seq = 1;
        if ($last) {
            $seq = (int) substr($last, -6) + 1;
        }

        return 'INV-'.$year.'-'.str_pad($seq, 6, '0', STR_PAD_LEFT);
    }
}
