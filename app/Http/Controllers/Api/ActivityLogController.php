<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    /**
     * Get activity logs with filtering and pagination
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user:id,name,email,role')
            ->orderBy('created_at', 'desc');

        // Filter by user
        if ($request->has('user_id')) {
            $query->byUser($request->user_id);
        }

        // Filter by action
        if ($request->has('action')) {
            $query->byAction($request->action);
        }

        // Filter by entity type
        if ($request->has('entity_type')) {
            $query->byEntity($request->entity_type, $request->entity_id);
        }

        // Filter by severity
        if ($request->has('severity')) {
            $query->bySeverity($request->severity);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        // Search in description
        if ($request->has('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $perPage = $request->input('per_page', 50);
        $logs = $query->paginate($perPage);

        // Transform the response
        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'role' => $log->user->role,
                ] : null,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'description' => $log->description,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'severity' => $log->severity,
                'created_at' => $log->created_at->toISOString(),
            ];
        });

        return response()->json([
            'message' => 'Activity logs retrieved successfully',
            'data' => $logs
        ]);
    }

    /**
     * Get activity logs for a specific entity
     */
    public function byEntity(Request $request, string $entityType, int $entityId)
    {
        $logs = ActivityLog::with('user:id,name,email,role')
            ->byEntity($entityType, $entityId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'role' => $log->user->role,
                ] : null,
                'action' => $log->action,
                'description' => $log->description,
                'metadata' => $log->metadata,
                'severity' => $log->severity,
                'created_at' => $log->created_at->toISOString(),
            ];
        });

        return response()->json([
            'message' => 'Entity activity logs retrieved successfully',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'data' => $logs
        ]);
    }

    /**
     * Get activity log statistics
     */
    public function stats(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        $stats = [
            'total_activities' => ActivityLog::dateRange($startDate, $endDate)->count(),

            'by_action' => ActivityLog::dateRange($startDate, $endDate)
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->get()
                ->pluck('count', 'action'),

            'by_severity' => ActivityLog::dateRange($startDate, $endDate)
                ->selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')
                ->get()
                ->pluck('count', 'severity'),

            'by_entity_type' => ActivityLog::dateRange($startDate, $endDate)
                ->whereNotNull('entity_type')
                ->selectRaw('entity_type, COUNT(*) as count')
                ->groupBy('entity_type')
                ->orderBy('count', 'desc')
                ->get()
                ->pluck('count', 'entity_type'),

            'most_active_users' => ActivityLog::with('user:id,name,email')
                ->dateRange($startDate, $endDate)
                ->selectRaw('user_id, COUNT(*) as activity_count')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->orderBy('activity_count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'user' => $log->user ? [
                            'id' => $log->user->id,
                            'name' => $log->user->name,
                            'email' => $log->user->email,
                        ] : null,
                        'activity_count' => $log->activity_count,
                    ];
                }),

            'critical_activities' => ActivityLog::dateRange($startDate, $endDate)
                ->bySeverity('critical')
                ->count(),

            'error_activities' => ActivityLog::dateRange($startDate, $endDate)
                ->bySeverity('error')
                ->count(),
        ];

        return response()->json([
            'message' => 'Activity log statistics retrieved successfully',
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'stats' => $stats
        ]);
    }

    /**
     * Get recent critical/error activities
     */
    public function criticalActivities(Request $request)
    {
        $limit = $request->input('limit', 20);

        $logs = ActivityLog::with('user:id,name,email,role')
            ->whereIn('severity', ['critical', 'error'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ] : null,
                    'action' => $log->action,
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'description' => $log->description,
                    'metadata' => $log->metadata,
                    'severity' => $log->severity,
                    'created_at' => $log->created_at->toISOString(),
                ];
            });

        return response()->json([
            'message' => 'Critical activities retrieved successfully',
            'data' => $logs
        ]);
    }

    /**
     * Get single activity log details
     */
    public function show($id)
    {
        $log = ActivityLog::with('user:id,name,email,role,phone')->findOrFail($id);

        return response()->json([
            'message' => 'Activity log retrieved successfully',
            'data' => [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'role' => $log->user->role,
                    'phone' => $log->user->phone,
                ] : null,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'description' => $log->description,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'severity' => $log->severity,
                'created_at' => $log->created_at->toISOString(),
                'updated_at' => $log->updated_at->toISOString(),
            ]
        ]);
    }

    /**
     * Delete old activity logs (cleanup)
     */
    public function cleanup(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:30', // Minimum 30 days retention
        ]);

        $cutoffDate = Carbon::now()->subDays($request->days);

        $deletedCount = ActivityLog::where('created_at', '<', $cutoffDate)
            ->where('severity', 'info') // Only delete info logs, keep warnings/errors/critical
            ->delete();

        ActivityLog::log(
            'cleanup',
            "Cleaned up {$deletedCount} activity logs older than {$request->days} days",
            null,
            null,
            ['deleted_count' => $deletedCount, 'days' => $request->days],
            'info'
        );

        return response()->json([
            'message' => 'Activity logs cleaned up successfully',
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);
    }
}
