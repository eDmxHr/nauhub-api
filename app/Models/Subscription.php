<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_type',
        'amount',
        'currency',
        'start_date',
        'end_date',
        'status',
        'auto_renew',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'auto_renew' => 'boolean',
    ];

    // Subscription belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Check if subscription is active
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->end_date >= now();
    }

    // Check if subscription is expired
    public function isExpired(): bool
    {
        return $this->end_date < now();
    }
}
