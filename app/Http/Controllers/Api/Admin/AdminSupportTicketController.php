<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSupportTicketController extends Controller
{
    /**
     * Get all support tickets with filters
     *
     * GET /api/admin/support/tickets
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $status = $request->input('status');
        $category = $request->input('category');
        $priority = $request->input('priority');
        $assignedTo = $request->input('assigned_to');
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'newest');

        $query = SupportTicket::with(['user:id,name,email', 'assignedTo:id,name']);

        // Apply filters
        if ($status) {
            $query->where('status', $status);
        }

        if ($category) {
            $query->where('category', $category);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($assignedTo) {
            if ($assignedTo === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $assignedTo);
            }
        }

        // Search by ticket number, subject, or user
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Apply sorting
        switch ($sortBy) {
            case 'oldest':
                $query->oldest();
                break;
            case 'priority':
                $query->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')");
                break;
            case 'last_activity':
                $query->orderBy('last_activity_at', 'desc');
                break;
            case 'newest':
            default:
                $query->latest();
                break;
        }

        $tickets = $query->paginate($perPage);

        return response()->json([
            'message' => 'Tickets retrieved successfully',
            'tickets' => $tickets->items(),
            'pagination' => [
                'total' => $tickets->total(),
                'per_page' => $tickets->perPage(),
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
            ],
        ]);
    }

    /**
     * Get ticket statistics
     *
     * GET /api/admin/support/stats
     */
    public function stats()
    {
        $stats = [
            'total' => SupportTicket::count(),
            'open' => SupportTicket::where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'awaiting_reply' => SupportTicket::where('status', 'awaiting_reply')->count(),
            'resolved' => SupportTicket::where('status', 'resolved')->count(),
            'closed' => SupportTicket::where('status', 'closed')->count(),
            'unassigned' => SupportTicket::whereNull('assigned_to')->whereNotIn('status', ['resolved', 'closed'])->count(),
            'by_category' => SupportTicket::selectRaw('category, count(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category'),
            'by_priority' => SupportTicket::selectRaw('priority, count(*) as count')
                ->whereNotIn('status', ['resolved', 'closed'])
                ->groupBy('priority')
                ->pluck('count', 'priority'),
        ];

        return response()->json($stats);
    }

    /**
     * Get a specific ticket with all details
     *
     * GET /api/admin/support/tickets/{ticketNumber}
     */
    public function show(string $ticketNumber)
    {
        $ticket = SupportTicket::where('ticket_number', $ticketNumber)
            ->with([
                'user:id,name,email,phone,is_subscribed,created_at',
                'assignedTo:id,name,email',
                'resolvedBy:id,name',
                'replies' => function ($query) {
                    $query->orderBy('created_at', 'asc');
                },
                'replies.user:id,name,role',
            ])
            ->first();

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Ticket retrieved successfully',
            'ticket' => $ticket,
            'categories' => SupportTicket::CATEGORIES,
            'priorities' => SupportTicket::PRIORITIES,
            'statuses' => SupportTicket::STATUSES,
        ]);
    }

    /**
     * Assign ticket to an admin
     *
     * POST /api/admin/support/tickets/{ticketNumber}/assign
     */
    public function assign(Request $request, string $ticketNumber)
    {
        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)->first();

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found'
            ], 404);
        }

        $oldAssignee = $ticket->assigned_to;

        $ticket->update([
            'assigned_to' => $validated['assigned_to'],
            'assigned_at' => now(),
            'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status,
            'last_activity_at' => now(),
        ]);

        // Log assignment
        ActivityLog::log(
            'ticket_assigned',
            "Ticket {$ticket->ticket_number} assigned",
            'SupportTicket',
            $ticket->id,
            [
                'old_assignee' => $oldAssignee,
                'new_assignee' => $validated['assigned_to'],
                'assigned_by' => $request->user()->id,
            ],
            'info'
        );

        return response()->json([
            'message' => 'Ticket assigned successfully',
            'ticket' => $ticket->fresh(['assignedTo:id,name']),
        ]);
    }

    /**
     * Update ticket status
     *
     * PUT /api/admin/support/tickets/{ticketNumber}/status
     */
    public function updateStatus(Request $request, string $ticketNumber)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))],
        ]);

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)->first();

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found'
            ], 404);
        }

        $oldStatus = $ticket->status;

        $ticket->update([
            'status' => $validated['status'],
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'message' => 'Ticket status updated successfully',
            'ticket' => $ticket,
            'old_status' => $oldStatus,
        ]);
    }

    /**
     * Update ticket priority
     *
     * PUT /api/admin/support/tickets/{ticketNumber}/priority
     */
    public function updatePriority(Request $request, string $ticketNumber)
    {
        $validated = $request->validate([
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
        ]);

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)->first();

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found'
            ], 404);
        }

        $oldPriority = $ticket->priority;

        $ticket->update([
            'priority' => $validated['priority'],
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'message' => 'Ticket priority updated successfully',
            'ticket' => $ticket,
            'old_priority' => $oldPriority,
        ]);
    }

    /**
     * Add a reply to a ticket (admin)
     *
     * POST /api/admin/support/tickets/{ticketNumber}/reply
     */
    public function reply(Request $request, string $ticketNumber)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'is_internal_note' => 'nullable|boolean',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'image|max:5120',
        ]);

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)->first();

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found'
            ], 404);
        }

        // Handle attachments
        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentPaths[] = $file->store('support_attachments', 'public');
            }
        }

        $isInternalNote = $validated['is_internal_note'] ?? false;

        // Create reply
        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'is_admin_reply' => true,
            'is_internal_note' => $isInternalNote,
        ]);

        // Update ticket status if it's a customer-facing reply
        if (!$isInternalNote) {
            $ticket->update([
                'status' => 'awaiting_reply',
                'last_activity_at' => now(),
            ]);
        } else {
            $ticket->update([
                'last_activity_at' => now(),
            ]);
        }

        return response()->json([
            'message' => $isInternalNote ? 'Internal note added successfully' : 'Reply sent successfully',
            'reply' => $reply->load('user:id,name'),
        ], 201);
    }

    /**
     * Resolve a ticket
     *
     * POST /api/admin/support/tickets/{ticketNumber}/resolve
     */
    public function resolve(Request $request, string $ticketNumber)
    {
        $validated = $request->validate([
            'resolution_notes' => 'required|string|max:2000',
        ]);

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)->first();

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found'
            ], 404);
        }

        if ($ticket->status === 'resolved' || $ticket->status === 'closed') {
            return response()->json([
                'message' => 'Ticket is already resolved or closed'
            ], 400);
        }

        $ticket->update([
            'status' => 'resolved',
            'resolution_notes' => $validated['resolution_notes'],
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
            'last_activity_at' => now(),
        ]);

        // Add resolution as a reply
        SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => "Ticket resolved: {$validated['resolution_notes']}",
            'is_admin_reply' => true,
            'is_internal_note' => false,
        ]);

        // Log resolution
        ActivityLog::log(
            'ticket_resolved',
            "Ticket {$ticket->ticket_number} resolved",
            'SupportTicket',
            $ticket->id,
            [
                'resolution_notes' => $validated['resolution_notes'],
                'resolved_by' => $request->user()->id,
            ],
            'info'
        );

        return response()->json([
            'message' => 'Ticket resolved successfully',
            'ticket' => $ticket->fresh(['resolvedBy:id,name']),
        ]);
    }

    /**
     * Close a ticket
     *
     * POST /api/admin/support/tickets/{ticketNumber}/close
     */
    public function close(Request $request, string $ticketNumber)
    {
        $ticket = SupportTicket::where('ticket_number', $ticketNumber)->first();

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found'
            ], 404);
        }

        if ($ticket->status === 'closed') {
            return response()->json([
                'message' => 'Ticket is already closed'
            ], 400);
        }

        $ticket->update([
            'status' => 'closed',
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'message' => 'Ticket closed successfully',
            'ticket' => $ticket,
        ]);
    }

    /**
     * Bulk update tickets
     *
     * POST /api/admin/support/tickets/bulk-update
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ticket_ids' => 'required|array|min:1|max:50',
            'ticket_ids.*' => 'exists:support_tickets,id',
            'action' => ['required', Rule::in(['assign', 'update_status', 'update_priority', 'close'])],
            'value' => 'required_unless:action,close',
        ]);

        $tickets = SupportTicket::whereIn('id', $validated['ticket_ids'])->get();
        $updated = 0;

        foreach ($tickets as $ticket) {
            switch ($validated['action']) {
                case 'assign':
                    $ticket->update([
                        'assigned_to' => $validated['value'],
                        'assigned_at' => now(),
                        'last_activity_at' => now(),
                    ]);
                    break;
                case 'update_status':
                    $ticket->update([
                        'status' => $validated['value'],
                        'last_activity_at' => now(),
                    ]);
                    break;
                case 'update_priority':
                    $ticket->update([
                        'priority' => $validated['value'],
                        'last_activity_at' => now(),
                    ]);
                    break;
                case 'close':
                    $ticket->update([
                        'status' => 'closed',
                        'last_activity_at' => now(),
                    ]);
                    break;
            }
            $updated++;
        }

        return response()->json([
            'message' => "{$updated} tickets updated successfully",
            'updated_count' => $updated,
        ]);
    }
}
