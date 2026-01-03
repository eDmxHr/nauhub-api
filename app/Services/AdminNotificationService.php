<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\ActivityLog;

class AdminNotificationService
{
    /**
     * Notify admins of a critical system error
     */
    public static function notifySystemError(
        string $errorMessage,
        array $errorDetails = [],
        ?int $activityLogId = null
    ): AdminNotification {
        return AdminNotification::notify(
            type: 'error',
            title: 'System Error Occurred',
            message: $errorMessage,
            priority: 'critical',
            data: $errorDetails,
            actionUrl: $activityLogId ? "/admin/activity-logs/{$activityLogId}" : '/admin/activity-logs?severity=critical',
            activityLogId: $activityLogId
        );
    }

    /**
     * Notify admins of pending payments
     */
    public static function notifyPendingPayment(
        int $paymentId,
        string $userName,
        float $amount
    ): AdminNotification {
        return AdminNotification::notify(
            type: 'payment',
            title: 'New Payment Pending Verification',
            message: "{$userName} submitted a payment of MVR {$amount} awaiting verification",
            priority: 'high',
            data: [
                'payment_id' => $paymentId,
                'user_name' => $userName,
                'amount' => $amount,
            ],
            actionUrl: "/admin/payments/{$paymentId}"
        );
    }

    /**
     * Notify admins of new user registration
     */
    public static function notifyNewUser(
        int $userId,
        string $userName,
        string $registrationMethod
    ): AdminNotification {
        return AdminNotification::notify(
            type: 'user',
            title: 'New User Registration',
            message: "New user '{$userName}' registered via {$registrationMethod}",
            priority: 'low',
            data: [
                'user_id' => $userId,
                'user_name' => $userName,
                'registration_method' => $registrationMethod,
            ],
            actionUrl: "/admin/users/{$userId}"
        );
    }

    /**
     * Notify admins of failed login attempts (potential security issue)
     */
    public static function notifyFailedLogins(
        string $email,
        int $attemptCount,
        string $ipAddress
    ): AdminNotification {
        return AdminNotification::notify(
            type: 'warning',
            title: 'Multiple Failed Login Attempts',
            message: "{$attemptCount} failed login attempts for '{$email}' from IP {$ipAddress}",
            priority: 'high',
            data: [
                'email' => $email,
                'attempt_count' => $attemptCount,
                'ip_address' => $ipAddress,
            ],
            actionUrl: "/admin/activity-logs?action=login_failed&search={$email}"
        );
    }

    /**
     * Notify admins of subscription expiring soon
     */
    public static function notifyExpiringSubscriptions(int $count): AdminNotification
    {
        return AdminNotification::notify(
            type: 'info',
            title: 'Subscriptions Expiring Soon',
            message: "{$count} subscriptions will expire in the next 7 days",
            priority: 'medium',
            data: ['count' => $count],
            actionUrl: '/admin/subscriptions?filter=expiring_soon'
        );
    }

    /**
     * Notify admins of video upload failures
     */
    public static function notifyVideoUploadFailed(
        int $videoId,
        string $title,
        string $error
    ): AdminNotification {
        return AdminNotification::notify(
            type: 'error',
            title: 'Video Upload Failed',
            message: "Upload failed for video '{$title}': {$error}",
            priority: 'medium',
            data: [
                'video_id' => $videoId,
                'title' => $title,
                'error' => $error,
            ],
            actionUrl: "/admin/videos/{$videoId}"
        );
    }

    /**
     * Get unread notification count
     */
    public static function getUnreadCount(): int
    {
        return AdminNotification::unread()->count();
    }

    /**
     * Get critical notification count
     */
    public static function getCriticalCount(): int
    {
        return AdminNotification::critical()->unread()->count();
    }

    /**
     * Mark all as read
     */
    public static function markAllAsRead(): int
    {
        return AdminNotification::unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Delete old read notifications
     */
    public static function cleanup(int $daysToKeep = 30): int
    {
        $cutoffDate = now()->subDays($daysToKeep);

        return AdminNotification::where('is_read', true)
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }
}
