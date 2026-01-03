<?php

/**
 * Quick test script to create sample notifications
 * Run with: php test-notifications.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\AdminNotificationService;
use App\Models\AdminNotification;

echo "Creating test notifications...\n\n";

// Test 1: System Error Notification
echo "1. Creating system error notification...\n";
$notification1 = AdminNotificationService::notifySystemError(
    'Test critical error: Database connection failed',
    [
        'exception' => 'PDOException',
        'url' => 'http://localhost:8000/api/admin/videos',
        'method' => 'GET',
    ]
);
echo "   ✓ Created notification ID: {$notification1->id}\n\n";

// Test 2: Pending Payment Notification
echo "2. Creating pending payment notification...\n";
$notification2 = AdminNotificationService::notifyPendingPayment(
    999,
    'John Doe',
    150.00
);
echo "   ✓ Created notification ID: {$notification2->id}\n\n";

// Test 3: New User Notification
echo "3. Creating new user notification...\n";
$notification3 = AdminNotificationService::notifyNewUser(
    888,
    'Jane Smith',
    'OTP'
);
echo "   ✓ Created notification ID: {$notification3->id}\n\n";

// Test 4: Failed Login Notification
echo "4. Creating failed login notification...\n";
$notification4 = AdminNotificationService::notifyFailedLogins(
    'attacker@example.com',
    5,
    '192.168.1.100'
);
echo "   ✓ Created notification ID: {$notification4->id}\n\n";

// Test 5: Expiring Subscriptions Notification
echo "5. Creating expiring subscriptions notification...\n";
$notification5 = AdminNotificationService::notifyExpiringSubscriptions(12);
echo "   ✓ Created notification ID: {$notification5->id}\n\n";

// Test 6: Video Upload Failed Notification
echo "6. Creating video upload failed notification...\n";
$notification6 = AdminNotificationService::notifyVideoUploadFailed(
    777,
    'Avengers: Endgame',
    'Cloudflare API timeout'
);
echo "   ✓ Created notification ID: {$notification6->id}\n\n";

// Display summary
echo "========================================\n";
echo "Summary:\n";
echo "========================================\n";
echo "Total notifications created: " . AdminNotification::count() . "\n";
echo "Unread notifications: " . AdminNotificationService::getUnreadCount() . "\n";
echo "Critical notifications: " . AdminNotificationService::getCriticalCount() . "\n\n";

echo "Notifications by type:\n";
$byType = AdminNotification::selectRaw('type, COUNT(*) as count')
    ->groupBy('type')
    ->get();
foreach ($byType as $item) {
    echo "  - {$item->type}: {$item->count}\n";
}

echo "\nNotifications by priority:\n";
$byPriority = AdminNotification::selectRaw('priority, COUNT(*) as count')
    ->groupBy('priority')
    ->get();
foreach ($byPriority as $item) {
    echo "  - {$item->priority}: {$item->count}\n";
}

echo "\n✅ Test notifications created successfully!\n";
echo "\nYou can now test the API endpoints:\n";
echo "  GET /api/admin/notifications\n";
echo "  GET /api/admin/notifications/stats\n";
echo "  GET /api/admin/notifications?is_read=false\n";
echo "  GET /api/admin/notifications?priority=critical\n";
