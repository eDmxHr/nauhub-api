<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Sanctum trait for API token authentication

class User extends Authenticatable
{
    /** 
     * Traits used by User model:
     * - HasApiTokens: Enables Sanctum token-based authentication
     * - HasFactory: Enables model factories for testing
     * - Notifiable: Enables sending notifications to users
     */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     * These fields can be filled using create() or update() methods
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',          // Phone number (7 digits)
        'phone_verified', // Phone verification status
        'role',           // 'user' or 'admin'
        'is_subscribed',  // Subscription status
        'is_active',      // Account active status (for blocking users)
    ];

    /**
     * The attributes that should be hidden for serialization.
     * These won't be included in JSON responses
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     * Converts database values to PHP types
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',  // Auto-hash passwords
            'phone_verified' => 'boolean',
            'is_subscribed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relationships
     */

    // User has many profiles
    public function profiles()
    {
        return $this->hasMany(Profile::class);
    }

    // User has many playlists
    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }

    // User has many subscriptions
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    // User has many payments
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // User has watch history
    public function watchHistory()
    {
        return $this->hasMany(WatchHistory::class);
    }

    /**
     * Helper methods
     */

    // Check if user is an admin
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Check if user has an active subscription
    public function hasActiveSubscription(): bool
    {
        return $this->is_subscribed;
    }

    // Get current active subscription
    public function currentSubscription()
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->orderBy('end_date', 'desc')
            ->first();
    }
}
