<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'amount',
        'currency',
        'reference_code',
        'proof_image',
        'status',
        'refund_status',
        'refunded_amount',
        'refund_reason',
        'refunded_at',
        'refunded_by',
        'admin_notes',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'verified_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    // Payment belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Payment belongs to a subscription
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    // Admin who verified the payment
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Admin who processed the refund
    public function refundedBy()
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    /**
     * Check if payment can be refunded
     */
    public function canBeRefunded(): bool
    {
        return $this->status === 'approved' && $this->refund_status !== 'refunded';
    }

    /**
     * Get remaining refundable amount
     */
    public function getRemainingRefundableAttribute(): float
    {
        return (float) $this->amount - (float) ($this->refunded_amount ?? 0);
    }
}
