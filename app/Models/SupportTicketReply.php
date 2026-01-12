<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicketReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'attachments',
        'is_admin_reply',
        'is_internal_note',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_admin_reply' => 'boolean',
        'is_internal_note' => 'boolean',
    ];

    /**
     * Relationships
     */

    // Reply belongs to a ticket
    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    // Reply belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */

    // Get only customer-visible replies (exclude internal notes)
    public function scopeCustomerVisible($query)
    {
        return $query->where('is_internal_note', false);
    }

    // Get only internal notes
    public function scopeInternalNotes($query)
    {
        return $query->where('is_internal_note', true);
    }
}
