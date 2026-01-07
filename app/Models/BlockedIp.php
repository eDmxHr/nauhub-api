<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BlockedIp extends Model
{
    protected $fillable = [
        'ip_address',
        'type',
        'block_type',
        'reason',
        'notes',
        'blocked_by_user_id',
        'expires_at',
        'failed_attempts',
        'last_attempt_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'is_active' => 'boolean',
        'failed_attempts' => 'integer',
    ];

    /**
     * Relationship: Admin who blocked this IP
     */
    public function blockedBy()
    {
        return $this->belongsTo(User::class, 'blocked_by_user_id');
    }

    /**
     * Check if an IP is blocked
     */
    public static function isBlocked(string $ipAddress): bool
    {
        return Cache::remember("blocked_ip.{$ipAddress}", 300, function () use ($ipAddress) {
            $blockedIp = self::where('ip_address', $ipAddress)
                ->where('is_active', true)
                ->first();

            if (!$blockedIp) {
                return false;
            }

            // Check if temporary block has expired
            if ($blockedIp->block_type === 'temporary' && $blockedIp->expires_at && $blockedIp->expires_at->isPast()) {
                $blockedIp->update(['is_active' => false]);
                Cache::forget("blocked_ip.{$ipAddress}");
                return false;
            }

            return true;
        });
    }

    /**
     * Block an IP address
     */
    public static function blockIp(
        string $ipAddress,
        string $type = 'manual',
        string $blockType = 'permanent',
        ?string $reason = null,
        ?string $notes = null,
        ?int $blockedByUserId = null,
        ?\DateTimeInterface $expiresAt = null
    ): self {
        $blockedIp = self::updateOrCreate(
            ['ip_address' => $ipAddress],
            [
                'type' => $type,
                'block_type' => $blockType,
                'reason' => $reason,
                'notes' => $notes,
                'blocked_by_user_id' => $blockedByUserId ?? auth()->id(),
                'expires_at' => $expiresAt,
                'is_active' => true,
            ]
        );

        // Clear cache
        Cache::forget("blocked_ip.{$ipAddress}");

        // Log the block
        ActivityLog::log(
            'ip_blocked',
            "IP address {$ipAddress} has been blocked",
            'BlockedIp',
            $blockedIp->id,
            [
                'ip_address' => $ipAddress,
                'type' => $type,
                'block_type' => $blockType,
                'reason' => $reason,
            ],
            'warning'
        );

        return $blockedIp;
    }

    /**
     * Unblock an IP address
     */
    public static function unblockIp(string $ipAddress): bool
    {
        $blockedIp = self::where('ip_address', $ipAddress)->first();

        if ($blockedIp) {
            $blockedIp->update(['is_active' => false]);
            Cache::forget("blocked_ip.{$ipAddress}");

            // Log the unblock
            ActivityLog::log(
                'ip_unblocked',
                "IP address {$ipAddress} has been unblocked",
                'BlockedIp',
                $blockedIp->id,
                ['ip_address' => $ipAddress],
                'info'
            );

            return true;
        }

        return false;
    }

    /**
     * Track failed login attempt
     */
    public static function trackFailedAttempt(string $ipAddress): void
    {
        $blockedIp = self::firstOrCreate(
            ['ip_address' => $ipAddress],
            [
                'type' => 'failed_login',
                'block_type' => 'temporary',
                'is_active' => false,
            ]
        );

        $blockedIp->increment('failed_attempts');
        $blockedIp->update(['last_attempt_at' => now()]);

        // Auto-block after max attempts
        $maxAttempts = SystemConfig::get('security.max_login_attempts', 5);
        $blockDurationMinutes = SystemConfig::get('security.block_duration_minutes', 30);

        if ($blockedIp->failed_attempts >= $maxAttempts && !$blockedIp->is_active) {
            $blockedIp->update([
                'is_active' => true,
                'type' => 'failed_login',
                'block_type' => 'temporary',
                'reason' => "Automatically blocked after {$maxAttempts} failed login attempts",
                'expires_at' => now()->addMinutes($blockDurationMinutes),
            ]);

            Cache::forget("blocked_ip.{$ipAddress}");

            // Log auto-block
            ActivityLog::log(
                'ip_auto_blocked',
                "IP address {$ipAddress} automatically blocked after {$maxAttempts} failed login attempts",
                'BlockedIp',
                $blockedIp->id,
                [
                    'ip_address' => $ipAddress,
                    'failed_attempts' => $blockedIp->failed_attempts,
                    'expires_at' => $blockedIp->expires_at,
                ],
                'warning'
            );

            // Notify admins
            AdminNotificationService::notify(
                'security',
                'IP Auto-blocked',
                "IP {$ipAddress} has been automatically blocked after {$maxAttempts} failed login attempts.",
                'high',
                ['ip_address' => $ipAddress, 'failed_attempts' => $blockedIp->failed_attempts]
            );
        }
    }

    /**
     * Reset failed attempts for an IP
     */
    public static function resetFailedAttempts(string $ipAddress): void
    {
        $blockedIp = self::where('ip_address', $ipAddress)->first();

        if ($blockedIp) {
            $blockedIp->update([
                'failed_attempts' => 0,
                'last_attempt_at' => null,
            ]);
        }
    }

    /**
     * Scope: Active blocks
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Expired blocks
     */
    public function scopeExpired($query)
    {
        return $query->where('block_type', 'temporary')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /**
     * Scope: By type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Clean up expired temporary blocks
     */
    public static function cleanupExpired(): int
    {
        $count = self::expired()->update(['is_active' => false]);

        if ($count > 0) {
            ActivityLog::log(
                'expired_blocks_cleaned',
                "Cleaned up {$count} expired IP blocks",
                null,
                null,
                ['count' => $count],
                'info'
            );
        }

        return $count;
    }
}
