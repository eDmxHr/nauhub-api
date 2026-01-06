<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockedIpMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ipAddress = $request->ip();

        // Check if IP is blocked
        if (BlockedIp::isBlocked($ipAddress)) {
            // Get block details
            $blockedIp = BlockedIp::where('ip_address', $ipAddress)
                ->where('is_active', true)
                ->first();

            // Log blocked attempt
            ActivityLog::log(
                'blocked_ip_attempt',
                "Blocked IP {$ipAddress} attempted to access: {$request->path()}",
                'BlockedIp',
                $blockedIp?->id,
                [
                    'ip_address' => $ipAddress,
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'user_agent' => $request->userAgent(),
                    'block_reason' => $blockedIp?->reason,
                ],
                'warning'
            );

            return response()->json([
                'message' => 'Access denied. Your IP address has been blocked.',
                'blocked' => true,
            ], 403);
        }

        return $next($request);
    }
}
