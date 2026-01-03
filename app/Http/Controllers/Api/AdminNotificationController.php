<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\AdminNotificationService;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    /**
     * Get all admin notifications with filtering
     */
    public function index(Request $request)
    {
        $query = AdminNotification::with('activityLog')
            ->orderBy('created_at', 'desc');

        // Filter by read status
        if ($request->has('is_read')) {
            if ($request->is_read === 'true' || $request->is_read === '1') {
                $query->read();
            } else {
                $query->unread();
            }
        }

        // Filter by type
        if ($request->has('type')) {
            $query->ofType($request->type);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->byPriority($request->priority);
        }

        // Filter critical only
        if ($request->has('critical') && $request->critical) {
            $query->critical();
        }

        $perPage = $request->input('per_page', 20);
        $notifications = $query->paginate($perPage);

        $notifications->getCollection()->transform(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => $notification->data,
                'action_url' => $notification->action_url,
                'priority' => $notification->priority,
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at->toISOString(),
            ];
        });

        return response()->json([
            'message' => 'Notifications retrieved successfully',
            'data' => $notifications
        ]);
    }

    /**
     * Get notification statistics
     */
    public function stats()
    {
        $stats = [
            'total' => AdminNotification::count(),
            'unread' => AdminNotificationService::getUnreadCount(),
            'critical_unread' => AdminNotificationService::getCriticalCount(),
            'by_type' => AdminNotification::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->get()
                ->pluck('count', 'type'),
            'by_priority' => AdminNotification::selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->get()
                ->pluck('count', 'priority'),
            'unread_by_priority' => AdminNotification::unread()
                ->selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->get()
                ->pluck('count', 'priority'),
        ];

        return response()->json([
            'message' => 'Notification statistics retrieved successfully',
            'stats' => $stats
        ]);
    }

    /**
     * Get single notification
     */
    public function show($id)
    {
        $notification = AdminNotification::with('activityLog')->findOrFail($id);

        // Auto-mark as read when viewed
        if (!$notification->is_read) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Notification retrieved successfully',
            'data' => [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'data' => $notification->data,
                'action_url' => $notification->action_url,
                'priority' => $notification->priority,
                'is_read' => $notification->is_read,
                'read_at' => $notification->read_at?->toISOString(),
                'activity_log' => $notification->activityLog ? [
                    'id' => $notification->activityLog->id,
                    'action' => $notification->activityLog->action,
                    'description' => $notification->activityLog->description,
                    'severity' => $notification->activityLog->severity,
                ] : null,
                'created_at' => $notification->created_at->toISOString(),
            ]
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id)
    {
        $notification = AdminNotification::findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread($id)
    {
        $notification = AdminNotification::findOrFail($id);
        $notification->markAsUnread();

        return response()->json([
            'message' => 'Notification marked as unread'
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $count = AdminNotificationService::markAllAsRead();

        return response()->json([
            'message' => 'All notifications marked as read',
            'count' => $count
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy($id)
    {
        $notification = AdminNotification::findOrFail($id);
        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully'
        ]);
    }

    /**
     * Cleanup old read notifications
     */
    public function cleanup(Request $request)
    {
        $request->validate([
            'days' => 'nullable|integer|min:7|max:365',
        ]);

        $days = $request->input('days', 30);
        $count = AdminNotificationService::cleanup($days);

        return response()->json([
            'message' => 'Old notifications cleaned up successfully',
            'deleted_count' => $count,
            'days_kept' => $days
        ]);
    }
}
