<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    /**
     * Get all tickets for the authenticated user
     *
     * GET /api/support/tickets
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $status = $request->input('status');
        $category = $request->input('category');

        $query = SupportTicket::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($category) {
            $query->where('category', $category);
        }

        $tickets = $query->paginate(10);

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
     * Get ticket categories for dropdown
     *
     * GET /api/support/categories
     */
    public function categories()
    {
        return response()->json([
            'categories' => SupportTicket::CATEGORIES,
            'priorities' => SupportTicket::PRIORITIES,
        ]);
    }

    /**
     * Create a new support ticket
     *
     * POST /api/support/tickets
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'priority' => ['nullable', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'image|max:5120', // 5MB max per image
        ]);

        $userId = $request->user()->id;

        // Handle attachments
        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentPaths[] = $file->store('support_attachments', 'public');
            }
        }

        // Create ticket
        $ticket = SupportTicket::create([
            'ticket_number' => SupportTicket::generateTicketNumber(),
            'user_id' => $userId,
            'category' => $validated['category'],
            'priority' => $validated['priority'] ?? 'medium',
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'status' => 'open',
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'message' => 'Support ticket created successfully',
            'ticket' => $ticket,
        ], 201);
    }

    /**
     * Get a specific ticket with replies
     *
     * GET /api/support/tickets/{ticketNumber}
     */
    public function show(Request $request, string $ticketNumber)
    {
        $userId = $request->user()->id;

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)
            ->where('user_id', $userId)
            ->with([
                'replies' => function ($query) {
                    $query->customerVisible()->orderBy('created_at', 'asc');
                },
                'replies.user:id,name',
                'assignedTo:id,name',
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
        ]);
    }

    /**
     * Add a reply to a ticket
     *
     * POST /api/support/tickets/{ticketNumber}/reply
     */
    public function reply(Request $request, string $ticketNumber)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'image|max:5120',
        ]);

        $userId = $request->user()->id;

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)
            ->where('user_id', $userId)
            ->first();

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found'
            ], 404);
        }

        if (!$ticket->canBeUpdatedByCustomer()) {
            return response()->json([
                'message' => 'This ticket is closed and cannot receive new replies'
            ], 400);
        }

        // Handle attachments
        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentPaths[] = $file->store('support_attachments', 'public');
            }
        }

        // Create reply
        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message' => $validated['message'],
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'is_admin_reply' => false,
            'is_internal_note' => false,
        ]);

        // Update ticket status and last activity
        $ticket->update([
            'status' => $ticket->status === 'awaiting_reply' ? 'in_progress' : $ticket->status,
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'message' => 'Reply added successfully',
            'reply' => $reply->load('user:id,name'),
        ], 201);
    }

    /**
     * Close a ticket (by customer)
     *
     * POST /api/support/tickets/{ticketNumber}/close
     */
    public function close(Request $request, string $ticketNumber)
    {
        $userId = $request->user()->id;

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)
            ->where('user_id', $userId)
            ->first();

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found'
            ], 404);
        }

        if (in_array($ticket->status, ['closed'])) {
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
     * Reopen a resolved ticket
     *
     * POST /api/support/tickets/{ticketNumber}/reopen
     */
    public function reopen(Request $request, string $ticketNumber)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $userId = $request->user()->id;

        $ticket = SupportTicket::where('ticket_number', $ticketNumber)
            ->where('user_id', $userId)
            ->first();

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket not found'
            ], 404);
        }

        if (!in_array($ticket->status, ['resolved', 'closed'])) {
            return response()->json([
                'message' => 'Only resolved or closed tickets can be reopened'
            ], 400);
        }

        // Reopen ticket
        $ticket->update([
            'status' => 'open',
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution_notes' => null,
            'last_activity_at' => now(),
        ]);

        // Add a reply explaining why it was reopened
        SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message' => "Ticket reopened: {$validated['reason']}",
            'is_admin_reply' => false,
            'is_internal_note' => false,
        ]);

        return response()->json([
            'message' => 'Ticket reopened successfully',
            'ticket' => $ticket,
        ]);
    }
}
