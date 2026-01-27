<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'category',
        'priority',
        'subject',
        'description',
        'attachments',
        'status',
        'assigned_to',
        'assigned_at',
        'resolution_notes',
        'resolved_at',
        'resolved_by',
        'last_activity_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'assigned_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    // Categories with labels
    public const CATEGORIES = [
        'movie_request' => 'Movie Request',
        'series_request' => 'Series Request',
        'technical_issue' => 'Technical Issue',
        'billing' => 'Billing & Payments',
        'account' => 'Account Issues',
        'feedback' => 'Feedback',
        'other' => 'Other',
    ];

    // Priority levels
    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    // Status labels
    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'awaiting_reply' => 'Awaiting Reply',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    /**
     * Generate a unique ticket number
     */
    public static function generateTicketNumber(): string
    {
        $year = now()->format('Y');
        $lastTicket = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastTicket ? (int) substr($lastTicket->ticket_number, -5) + 1 : 1;

        return sprintf('TKT-%s-%05d', $year, $sequence);
    }

    /**
     * Relationships
     */

    // Ticket belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Ticket assigned to admin
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Ticket resolved by admin
    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Ticket has many replies
    public function replies()
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id');
    }

    /**
     * Scopes
     */

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNotIn('status', ['resolved', 'closed']);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Accessors
     */

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Check if ticket can be updated by customer
     */
    public function canBeUpdatedByCustomer(): bool
    {
        return !in_array($this->status, ['resolved', 'closed']);
    }
}
