<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'tier',
        'name',
        'duration',
        'price',
        'currency',
        'duration_days',
        'features',
        'is_popular',
        'savings_text',
        'discount_text',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'features' => 'array',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope to get only active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get subscriptions using this plan
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_type', 'duration')
            ->where('subscriptions.plan_type', $this->duration);
    }
}
