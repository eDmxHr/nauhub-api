<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IpBlockController extends Controller
{
    /**
     * Get all blocked IPs with filters
     * GET /api/admin/blocked-ips
     */
    public function index(Request $request)
    {
        $query = BlockedIp::with('blockedBy:id,name,email');

        // Filter by type
        if ($request->has('type')) {
            $query->ofType($request->type);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by block type
        if ($request->has('block_type')) {
            $query->where('block_type', $request->block_type);
        }

        // Search by IP address
        if ($request->has('search')) {
            $query->where('ip_address', 'like', '%' . $request->search . '%');
        }

        $blockedIps = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'message' => 'Blocked IPs retrieved successfully',
            'data' => $blockedIps
        ]);
    }

    /**
     * Get statistics about blocked IPs
     * GET /api/admin/blocked-ips/stats
     */
    public function stats()
    {
        $stats = [
            'total_blocked' => BlockedIp::active()->count(),
            'permanent_blocks' => BlockedIp::active()->where('block_type', 'permanent')->count(),
            'temporary_blocks' => BlockedIp::active()->where('block_type', 'temporary')->count(),
            'by_type' => [
                'manual' => BlockedIp::active()->ofType('manual')->count(),
                'failed_login' => BlockedIp::active()->ofType('failed_login')->count(),
                'bot_spam' => BlockedIp::active()->ofType('bot_spam')->count(),
                'security_threat' => BlockedIp::active()->ofType('security_threat')->count(),
            ],
            'expired_pending_cleanup' => BlockedIp::expired()->where('is_active', true)->count(),
        ];

        return response()->json([
            'message' => 'IP blocking statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * Block an IP address
     * POST /api/admin/blocked-ips
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'ip', 'unique:blocked_ips,ip_address'],
            'type' => ['required', Rule::in(['manual', 'bot_spam', 'security_threat'])],
            'block_type' => ['required', Rule::in(['permanent', 'temporary'])],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now', 'required_if:block_type,temporary'],
        ]);

        $blockedIp = BlockedIp::blockIp(
            $validated['ip_address'],
            $validated['type'],
            $validated['block_type'],
            $validated['reason'],
            $validated['notes'] ?? null,
            auth()->id(),
            isset($validated['expires_at']) ? new \DateTime($validated['expires_at']) : null
        );

        return response()->json([
            'message' => 'IP address blocked successfully',
            'data' => $blockedIp->load('blockedBy:id,name,email')
        ], 201);
    }

    /**
     * Get a specific blocked IP
     * GET /api/admin/blocked-ips/{id}
     */
    public function show($id)
    {
        $blockedIp = BlockedIp::with('blockedBy:id,name,email')->findOrFail($id);

        return response()->json([
            'message' => 'Blocked IP retrieved successfully',
            'data' => $blockedIp
        ]);
    }

    /**
     * Update a blocked IP
     * PUT /api/admin/blocked-ips/{id}
     */
    public function update(Request $request, $id)
    {
        $blockedIp = BlockedIp::findOrFail($id);

        $validated = $request->validate([
            'type' => ['sometimes', Rule::in(['manual', 'failed_login', 'bot_spam', 'security_threat'])],
            'block_type' => ['sometimes', Rule::in(['permanent', 'temporary'])],
            'reason' => ['sometimes', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $blockedIp->update($validated);

        // Clear cache if IP address is modified
        \Cache::forget("blocked_ip.{$blockedIp->ip_address}");

        return response()->json([
            'message' => 'Blocked IP updated successfully',
            'data' => $blockedIp->fresh()->load('blockedBy:id,name,email')
        ]);
    }

    /**
     * Unblock an IP address
     * DELETE /api/admin/blocked-ips/{id}
     */
    public function destroy($id)
    {
        $blockedIp = BlockedIp::findOrFail($id);
        $ipAddress = $blockedIp->ip_address;

        BlockedIp::unblockIp($ipAddress);

        return response()->json([
            'message' => 'IP address unblocked successfully'
        ]);
    }

    /**
     * Bulk unblock multiple IPs
     * POST /api/admin/blocked-ips/bulk-unblock
     */
    public function bulkUnblock(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:blocked_ips,id'],
        ]);

        $unblocked = [];

        foreach ($validated['ids'] as $id) {
            $blockedIp = BlockedIp::find($id);
            if ($blockedIp) {
                BlockedIp::unblockIp($blockedIp->ip_address);
                $unblocked[] = $blockedIp->ip_address;
            }
        }

        return response()->json([
            'message' => 'IPs unblocked successfully',
            'unblocked' => $unblocked,
            'count' => count($unblocked)
        ]);
    }

    /**
     * Clean up expired temporary blocks
     * POST /api/admin/blocked-ips/cleanup
     */
    public function cleanup()
    {
        $count = BlockedIp::cleanupExpired();

        return response()->json([
            'message' => 'Expired blocks cleaned up successfully',
            'count' => $count
        ]);
    }

    /**
     * Check if a specific IP is blocked
     * GET /api/admin/blocked-ips/check/{ip}
     */
    public function check($ip)
    {
        $request = request();
        $request->merge(['ip' => $ip]);

        $validated = $request->validate([
            'ip' => ['required', 'ip'],
        ]);

        $isBlocked = BlockedIp::isBlocked($validated['ip']);
        $blockedIp = null;

        if ($isBlocked) {
            $blockedIp = BlockedIp::where('ip_address', $validated['ip'])
                ->where('is_active', true)
                ->with('blockedBy:id,name,email')
                ->first();
        }

        return response()->json([
            'message' => 'IP check completed',
            'is_blocked' => $isBlocked,
            'data' => $blockedIp
        ]);
    }

    /**
     * Reset failed login attempts for an IP
     * POST /api/admin/blocked-ips/{id}/reset-attempts
     */
    public function resetAttempts($id)
    {
        $blockedIp = BlockedIp::findOrFail($id);

        BlockedIp::resetFailedAttempts($blockedIp->ip_address);

        return response()->json([
            'message' => 'Failed login attempts reset successfully',
            'data' => $blockedIp->fresh()
        ]);
    }
}
