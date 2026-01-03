<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $fillable = [
        'type',
        'title',
        'message',
        'data',
        'action_url',
        'priority',
        'is_read',
        'read_at',
        'related_activity_log_id',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Related activity log
     */
    public function activityLog()
    {
        return $this->belongsTo(ActivityLog::class, 'related_activity_log_id');
    }

    /**
     * Static helper to create a notification
     */
    public static function notify(
        string $type,
        string $title,
        string $message,
        string $priority = 'medium',
        ?array $data = null,
        ?string $actionUrl = null,
        ?int $activityLogId = null
    ): self {
        return self::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'priority' => $priority,
            'data' => $data,
            'action_url' => $actionUrl,
            'related_activity_log_id' => $activityLogId,
        ]);
    }

    /**
     * Mark as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark as unread
     */
    public function markAsUnread(): void
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for critical/high priority
     */
    public function scopeCritical($query)
    {
        return $query->whereIn('priority', ['critical', 'high']);
    }
}
