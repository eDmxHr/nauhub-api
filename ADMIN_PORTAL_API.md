# Admin Portal API Documentation

Complete API reference for the admin portal (management dashboard).

**All admin endpoints require:**
- `Authorization: Bearer {admin_token}` header
- User must have `role = 'admin'`

---

## Table of Contents
1. [Authentication](#authentication)
2. [Dashboard & Statistics](#dashboard--statistics)
3. [User Management](#user-management)
4. [Content Management](#content-management)
5. [Subscription & Payment Management](#subscription--payment-management)
6. [Cloudflare & Video Upload](#cloudflare--video-upload)
7. [Subscription Plans](#subscription-plans)
8. [Support Tickets](#support-tickets)
9. [Activity Logs](#activity-logs)
10. [Notifications](#notifications)
11. [System Configuration](#system-configuration)
12. [IP Blocking](#ip-blocking)
13. [Integrations](#integrations)

---

## Authentication

### Admin Login
**POST** `/api/auth/login`

Login with email and password (admin only).

**Request:**
```json
{
  "email": "admin@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "message": "Login successful",
  "token": "2|xyz789...",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "role": "admin"
  }
}
```

---

## Dashboard & Statistics

### Get Dashboard Stats
**GET** `/api/admin/dashboard`

Get overview statistics for dashboard.

**Response:**
```json
{
  "users": {
    "total": 1250,
    "active": 1100,
    "subscribed": 450,
    "new_today": 15
  },
  "videos": {
    "total": 350,
    "movies": 200,
    "series": 150,
    "total_views": 45600
  },
  "subscriptions": {
    "active": 450,
    "pending": 12,
    "revenue_this_month": 112500.00
  },
  "support_tickets": {
    "open": 8,
    "in_progress": 5,
    "unassigned": 3
  }
}
```

---

### Revenue Chart
**GET** `/api/admin/dashboard/revenue-chart`

Get revenue data for charts (last 12 months).

---

### User Growth Chart
**GET** `/api/admin/dashboard/user-growth-chart`

Get user growth data for charts (last 12 months).

---

## User Management

### List Users
**GET** `/api/admin/users`

Get paginated list of users with filters.

**Query Parameters:**
- `per_page` - Items per page (default: 15)
- `role` - Filter by role: `user` or `admin`
- `is_subscribed` - Filter by subscription status (0 or 1)
- `is_active` - Filter by active status (0 or 1)
- `phone_verified` - Filter by phone verification (0 or 1)
- `search` - Search by name, email, or phone
- `sort_by` - Sort: `newest`, `oldest`, `name_asc`, `name_desc`

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Ahmed Hassan",
      "email": "ahmed@example.com",
      "phone": "7771234",
      "role": "user",
      "is_subscribed": true,
      "is_active": true,
      "phone_verified": true,
      "profiles": [...],
      "current_subscription": {
        "id": 1,
        "plan_type": "monthly",
        "end_date": "2026-02-01",
        "days_remaining": 20,
        "plan_name": "Premium Monthly"
      },
      "created_at": "2025-12-01T10:30:00.000000Z"
    }
  ],
  "pagination": { ... }
}
```

---

### Get User Statistics
**GET** `/api/admin/users/stats`

Get user statistics overview.

**Response:**
```json
{
  "total_users": 1250,
  "active_users": 1100,
  "blocked_users": 150,
  "total_admins": 5,
  "subscribed_users": 450,
  "phone_verified_users": 1000
}
```

---

### Get User Details
**GET** `/api/admin/users/{id}`

Get comprehensive details for a single user.

**Response:**
```json
{
  "id": 1,
  "name": "Ahmed Hassan",
  "email": "ahmed@example.com",
  "phone": "7771234",
  "is_subscribed": true,
  "current_subscription": {
    "id": 1,
    "plan_type": "monthly",
    "amount": 250.00,
    "start_date": "2026-01-01",
    "end_date": "2026-02-01",
    "days_remaining": 20,
    "plan_details": {
      "plan_id": "premium_monthly",
      "name": "Premium Monthly",
      "tier": "premium",
      "price": 250.00,
      "features": [...]
    }
  },
  "stats": {
    "total_subscriptions": 5,
    "active_subscriptions": 1,
    "total_payments": 5,
    "approved_payments": 4,
    "pending_payments": 1,
    "total_spent": 1000.00,
    "profile_count": 3,
    "watch_history_count": 45,
    "playlist_count": 2
  },
  "account_info": {
    "member_since": "2025-12-01 10:30:00",
    "member_for_days": 40,
    "last_updated": "2026-01-10 15:22:00"
  },
  "subscriptions": [...],
  "payments": [...],
  "profiles": [...],
  "watchHistory": [...],
  "playlists": [...]
}
```

---

### Update User Role
**PUT** `/api/admin/users/{id}/role`

Change user role between `user` and `admin`.

**Request:**
```json
{
  "role": "admin"
}
```

---

### Toggle User Active Status
**PUT** `/api/admin/users/{id}/toggle-active`

Block or unblock a user account.

**Response:**
```json
{
  "message": "User account blocked successfully",
  "is_active": false
}
```

---

## Content Management

### Categories

#### List Categories
**GET** `/api/categories`

#### Create Category
**POST** `/api/admin/categories`

**Request:**
```json
{
  "name": "Action",
  "slug": "action",
  "description": "Action movies and series"
}
```

#### Update Category
**PUT** `/api/admin/categories/{id}`

#### Delete Category
**DELETE** `/api/admin/categories/{id}`

---

### Genres

#### List Genres
**GET** `/api/genres`

#### Create Genre
**POST** `/api/admin/genres`

#### Update Genre
**PUT** `/api/admin/genres/{id}`

#### Delete Genre
**DELETE** `/api/admin/genres/{id}`

---

### Videos

#### List All Videos (Admin)
**GET** `/api/admin/videos`

Includes unpublished videos.

**Query Parameters:**
- Same as public `/api/videos` endpoint
- Additional filters for admin view

#### Create Video
**POST** `/api/admin/videos`

**Request:**
```json
{
  "title": "Inception",
  "description": "A mind-bending thriller...",
  "type": "movie",
  "video_uid": "abc123",
  "thumbnail": "https://...",
  "duration": 8880,
  "quality": "1080p",
  "release_date": "2010-07-16",
  "rating": 8.8,
  "is_published": true,
  "category_ids": [1, 2],
  "genre_ids": [3, 4]
}
```

#### Update Video
**PUT** `/api/admin/videos/{id}`

#### Delete Video
**DELETE** `/api/admin/videos/{id}`

---

### Seasons & Episodes

#### Create Season
**POST** `/api/admin/videos/{videoId}/seasons`

**Request:**
```json
{
  "season_number": 1,
  "title": "Season 1",
  "description": "The first season..."
}
```

#### Update Season
**PUT** `/api/admin/seasons/{id}`

#### Delete Season
**DELETE** `/api/admin/seasons/{id}`

#### Create Episode
**POST** `/api/admin/seasons/{seasonId}/episodes`

**Request:**
```json
{
  "episode_number": 1,
  "title": "Pilot",
  "description": "The first episode...",
  "video_uid": "xyz789",
  "duration": 2700,
  "quality": "1080p"
}
```

#### Update Episode
**PUT** `/api/admin/episodes/{id}`

#### Delete Episode
**DELETE** `/api/admin/episodes/{id}`

---

## Subscription & Payment Management

### Get Statistics
**GET** `/api/admin/subscriptions/stats`

Get comprehensive subscription and payment statistics.

**Response:**
```json
{
  "subscriptions": {
    "total": 450,
    "active": 420,
    "pending": 15,
    "expired": 10,
    "cancelled": 5
  },
  "payments": {
    "total": 500,
    "pending": 15,
    "approved": 470,
    "rejected": 15,
    "refunded": 5,
    "partially_refunded": 2
  },
  "revenue": {
    "total": 112500.00,
    "refunded": 1250.00,
    "net": 111250.00,
    "this_month": 22500.00
  }
}
```

---

### Subscriptions

#### List All Subscriptions
**GET** `/api/admin/subscriptions/all`

**Query Parameters:**
- `status` - Filter: `active`, `pending`, `expired`, `cancelled`
- `page` - Page number

#### Get Subscription Details
**GET** `/api/admin/subscriptions/{id}`

**Response:**
```json
{
  "subscription": {
    "id": 1,
    "user": { ... },
    "plan_type": "monthly",
    "amount": 250.00,
    "start_date": "2026-01-01",
    "end_date": "2026-02-01",
    "status": "active"
  },
  "payments": [...],
  "plan_details": { ... },
  "days_remaining": 20,
  "is_expired": false
}
```

#### Extend/Renew Subscription
**POST** `/api/admin/subscriptions/{id}/extend`

Manually extend or renew a user's subscription.

**Request (Option 1 - Specify days):**
```json
{
  "days": 30,
  "reason": "Compensation for service downtime",
  "is_free": true
}
```

**Request (Option 2 - Use plan):**
```json
{
  "plan_id": "premium_monthly",
  "reason": "Manual renewal",
  "is_free": false
}
```

**Response:**
```json
{
  "message": "Subscription extended successfully",
  "subscription": { ... },
  "extension_details": {
    "old_end_date": "2026-01-31",
    "new_end_date": "2026-03-02",
    "days_added": 30,
    "is_free": true
  }
}
```

---

### Payments

#### List All Payments
**GET** `/api/admin/payments/all`

**Query Parameters:**
- `status` - Filter: `pending`, `approved`, `rejected`
- `page` - Page number

#### Get Pending Payments
**GET** `/api/admin/payments/pending`

Get all payments awaiting approval.

#### Get Payment Details
**GET** `/api/admin/payments/{id}`

**Response:**
```json
{
  "payment": {
    "id": 1,
    "user": { ... },
    "subscription": { ... },
    "amount": 250.00,
    "currency": "MVR",
    "reference_code": "PAY-ABC123",
    "proof_image": "payment_proofs/...",
    "proof_image_url": "http://domain.com/storage/payment_proofs/...",
    "status": "pending",
    "refund_status": "none",
    "created_at": "2026-01-10T10:00:00.000000Z"
  }
}
```

#### Approve Payment
**POST** `/api/admin/payments/{id}/approve`

Approve payment and activate subscription.

**Request:**
```json
{
  "admin_notes": "Payment verified. Bank receipt confirmed."
}
```

**Response:**
```json
{
  "message": "Payment approved and subscription activated",
  "payment": { ... }
}
```

#### Reject Payment
**POST** `/api/admin/payments/{id}/reject`

Reject payment and cancel subscription.

**Request:**
```json
{
  "admin_notes": "Invalid payment proof. Please resubmit."
}
```

#### Refund Payment
**POST** `/api/admin/payments/{id}/refund`

Process full or partial refund for an approved payment.

**Request:**
```json
{
  "refund_amount": 250.00,
  "refund_type": "full",
  "reason": "Service not delivered. Customer requested refund.",
  "cancel_subscription": true
}
```

**Response:**
```json
{
  "message": "Refund processed successfully",
  "payment": { ... },
  "refund_details": {
    "refund_amount": 250.00,
    "total_refunded": 250.00,
    "refund_status": "refunded",
    "subscription_cancelled": true
  }
}
```

**Notes:**
- Supports partial refunds (can refund multiple times until full amount is refunded)
- Automatically updates `refund_status`: `none` → `partially_refunded` → `refunded`
- Can optionally cancel subscription when refunding

---

## Cloudflare & Video Upload

### Test Connection
**GET** `/api/admin/cloudflare/test-connection`

Test Cloudflare Stream API connection.

---

### Create Direct Upload URL
**POST** `/api/admin/cloudflare/create-direct-upload-url`

Generate a direct upload URL for client-side uploads (bypasses PHP).

**Request:**
```json
{
  "max_duration_seconds": 7200,
  "use_tus": true,
  "file_size": 500000000,
  "idempotency_key": "unique-key-123"
}
```

**Response (TUS Upload):**
```json
{
  "message": "TUS upload endpoint created successfully",
  "tus_endpoint": "https://upload.cloudflarestream.com/...",
  "video_uid": "abc123",
  "instructions": {
    "method": "Upload using TUS protocol",
    "recommended_chunk_size": "10-20 MB",
    "upload_url": "Use tus_endpoint above",
    "headers": {
      "Tus-Resumable": "1.0.0",
      "Upload-Offset": "0",
      "Content-Type": "application/offset+octet-stream"
    }
  }
}
```

---

### Check TUS Upload Progress
**POST** `/api/admin/cloudflare/check-tus-upload-progress`

Check progress of TUS upload.

**Request:**
```json
{
  "tus_endpoint": "https://upload.cloudflarestream.com/..."
}
```

---

### Upload Video (PHP - 40MB limit)
**POST** `/api/admin/cloudflare/upload-video`

Upload video file through PHP (limited to 40MB).

---

### Upload from URL
**POST** `/api/admin/cloudflare/upload-from-url`

Upload video from direct URL.

**Request:**
```json
{
  "url": "https://example.com/video.mp4",
  "max_duration_seconds": 7200
}
```

---

### Upload from Magnet
**POST** `/api/admin/cloudflare/upload-from-magnet`

Upload video from magnet link via Put.io.

**Request:**
```json
{
  "magnet_url": "magnet:?xt=urn:btih:...",
  "max_duration_seconds": 7200
}
```

---

### Get Video Details
**GET** `/api/admin/cloudflare/video/{videoUid}`

Get video metadata from Cloudflare.

---

### Get Upload Status
**GET** `/api/admin/cloudflare/video/{videoUid}/status`

Check upload and processing status.

---

### Delete Video
**DELETE** `/api/admin/cloudflare/video/{videoUid}`

Delete video from Cloudflare Stream.

---

## Subscription Plans

### List Plans
**GET** `/api/admin/plans`

Get all subscription plans.

---

### Create Plan
**POST** `/api/admin/plans`

**Request:**
```json
{
  "plan_id": "premium_monthly",
  "tier": "premium",
  "name": "Premium Monthly",
  "duration": "monthly",
  "price": 250.00,
  "currency": "MVR",
  "duration_days": 30,
  "features": ["4K Streaming", "5 Profiles", "Download"],
  "is_popular": true,
  "savings_text": "Save 20%",
  "discount_text": "Best Value!",
  "sort_order": 2,
  "is_active": true
}
```

---

### Update Plan
**PUT** `/api/admin/plans/{id}`

---

### Delete Plan
**DELETE** `/api/admin/plans/{id}`

---

### Toggle Plan Active Status
**PUT** `/api/admin/plans/{id}/toggle-active`

Enable or disable a plan.

---

## Support Tickets

### Get Statistics
**GET** `/api/admin/support/stats`

Get support ticket statistics.

**Response:**
```json
{
  "total": 150,
  "open": 25,
  "in_progress": 18,
  "awaiting_reply": 12,
  "resolved": 80,
  "closed": 15,
  "unassigned": 10,
  "by_category": {
    "movie_request": 45,
    "series_request": 30,
    "technical_issue": 25,
    "billing": 15
  },
  "by_priority": {
    "urgent": 5,
    "high": 15,
    "medium": 20,
    "low": 10
  }
}
```

---

### List All Tickets
**GET** `/api/admin/support/tickets`

**Query Parameters:**
- `per_page` - Items per page
- `status` - Filter by status
- `category` - Filter by category
- `priority` - Filter by priority
- `assigned_to` - Filter by assigned admin (or `unassigned`)
- `search` - Search by ticket number, subject, or user
- `sort_by` - Sort: `newest`, `oldest`, `priority`, `last_activity`

---

### Get Ticket Details
**GET** `/api/admin/support/tickets/{ticketNumber}`

**Response:**
```json
{
  "ticket": {
    "id": 1,
    "ticket_number": "TKT-2026-00001",
    "user": { ... },
    "category": "movie_request",
    "priority": "medium",
    "subject": "Request: Interstellar",
    "description": "...",
    "status": "in_progress",
    "assigned_to": { ... },
    "replies": [
      {
        "id": 1,
        "message": "We'll check availability!",
        "is_admin_reply": true,
        "is_internal_note": false,
        "user": { ... }
      }
    ]
  }
}
```

---

### Assign Ticket
**POST** `/api/admin/support/tickets/{ticketNumber}/assign`

**Request:**
```json
{
  "assigned_to": 2
}
```

---

### Update Status
**PUT** `/api/admin/support/tickets/{ticketNumber}/status`

**Request:**
```json
{
  "status": "in_progress"
}
```

---

### Update Priority
**PUT** `/api/admin/support/tickets/{ticketNumber}/priority`

**Request:**
```json
{
  "priority": "high"
}
```

---

### Reply to Ticket
**POST** `/api/admin/support/tickets/{ticketNumber}/reply`

**Request:**
```json
{
  "message": "We've added this movie to our platform!",
  "is_internal_note": false,
  "attachments": ["<file upload>"]
}
```

**Notes:**
- Set `is_internal_note: true` for admin-only notes (customer won't see)
- Customer-facing replies automatically set status to `awaiting_reply`

---

### Resolve Ticket
**POST** `/api/admin/support/tickets/{ticketNumber}/resolve`

**Request:**
```json
{
  "resolution_notes": "Movie has been added. Issue resolved."
}
```

---

### Close Ticket
**POST** `/api/admin/support/tickets/{ticketNumber}/close`

---

### Bulk Update Tickets
**POST** `/api/admin/support/tickets/bulk-update`

**Request:**
```json
{
  "ticket_ids": [1, 2, 3, 4, 5],
  "action": "assign",
  "value": 2
}
```

**Actions:**
- `assign` - Assign to admin (value = admin user ID)
- `update_status` - Change status (value = status)
- `update_priority` - Change priority (value = priority)
- `close` - Close tickets (no value needed)

---

## Activity Logs

### List Activity Logs
**GET** `/api/admin/activity-logs`

**Query Parameters:**
- `per_page` - Items per page
- `action` - Filter by action type
- `level` - Filter by level: `info`, `warning`, `error`, `critical`
- `entity_type` - Filter by entity: `User`, `Payment`, `Subscription`
- `entity_id` - Filter by specific entity ID
- `user_id` - Filter by user who performed action
- `date_from` / `date_to` - Date range

---

### Get Activity Stats
**GET** `/api/admin/activity-logs/stats`

---

### Get Critical Activities
**GET** `/api/admin/activity-logs/critical`

Get error and critical level activities.

---

### Get Logs for Entity
**GET** `/api/admin/activity-logs/entity/{entityType}/{entityId}`

Get all logs for a specific entity (e.g., User #1, Payment #5).

---

### Cleanup Old Logs
**DELETE** `/api/admin/activity-logs/cleanup`

Delete logs older than specified days.

**Request:**
```json
{
  "days": 90
}
```

---

## Notifications

### List Notifications
**GET** `/api/admin/notifications`

**Query Parameters:**
- `per_page` - Items per page
- `type` - Filter by type
- `priority` - Filter by priority
- `is_read` - Filter by read status (0 or 1)

---

### Get Notification Stats
**GET** `/api/admin/notifications/stats`

---

### Get Notification
**GET** `/api/admin/notifications/{id}`

Automatically marks as read.

---

### Mark as Read
**PUT** `/api/admin/notifications/{id}/read`

---

### Mark as Unread
**PUT** `/api/admin/notifications/{id}/unread`

---

### Mark All as Read
**PUT** `/api/admin/notifications/mark-all-read`

---

### Delete Notification
**DELETE** `/api/admin/notifications/{id}`

---

### Cleanup Old Notifications
**DELETE** `/api/admin/notifications/cleanup`

---

## System Configuration

### Get All Configs
**GET** `/api/admin/config`

Get all system configurations grouped.

---

### Get Config Groups
**GET** `/api/admin/config/groups`

---

### Get Single Config
**GET** `/api/admin/config/{key}`

---

### Update Config
**PUT** `/api/admin/config/{key}`

**Request:**
```json
{
  "value": "new_value"
}
```

---

### Bulk Update Configs
**PUT** `/api/admin/config`

**Request:**
```json
{
  "configs": {
    "site_name": "My Streaming Platform",
    "max_profiles_per_user": "5",
    "maintenance_mode": "false"
  }
}
```

---

### Clear Config Cache
**POST** `/api/admin/config/clear-cache`

---

## IP Blocking

### List Blocked IPs
**GET** `/api/admin/blocked-ips`

**Query Parameters:**
- `type` - Filter: `manual`, `failed_login`, `bot_spam`, `security_threat`
- `block_type` - Filter: `permanent`, `temporary`
- `is_active` - Filter by active status

---

### Get IP Blocking Stats
**GET** `/api/admin/blocked-ips/stats`

---

### Block IP
**POST** `/api/admin/blocked-ips`

**Request:**
```json
{
  "ip_address": "192.168.1.100",
  "type": "manual",
  "block_type": "permanent",
  "reason": "Suspicious activity detected"
}
```

---

### Check IP Status
**GET** `/api/admin/blocked-ips/check/{ip}`

---

### Update Blocked IP
**PUT** `/api/admin/blocked-ips/{id}`

---

### Unblock IP
**DELETE** `/api/admin/blocked-ips/{id}`

---

### Bulk Unblock IPs
**POST** `/api/admin/blocked-ips/bulk-unblock`

---

### Cleanup Expired Blocks
**POST** `/api/admin/blocked-ips/cleanup`

---

### Reset Failed Login Attempts
**POST** `/api/admin/blocked-ips/{id}/reset-attempts`

---

## Integrations

### List Integrations Status
**GET** `/api/admin/integrations`

---

### Test Cloudflare Connection
**POST** `/api/admin/integrations/cloudflare/test`

---

### Test Put.io Connection
**POST** `/api/admin/integrations/putio/test`

---

## Common Admin Actions Summary

### Subscription Management Flow
1. View pending payments: `GET /api/admin/payments/pending`
2. Review payment proof: `GET /api/admin/payments/{id}` (includes proof_image_url)
3. Approve or reject: `POST /api/admin/payments/{id}/approve` or `/reject`
4. If needed, extend manually: `POST /api/admin/subscriptions/{id}/extend`
5. Process refunds: `POST /api/admin/payments/{id}/refund`

### Support Ticket Workflow
1. View unassigned tickets: `GET /api/admin/support/tickets?assigned_to=unassigned`
2. Assign to self: `POST /api/admin/support/tickets/{ticketNumber}/assign`
3. Reply to customer: `POST /api/admin/support/tickets/{ticketNumber}/reply`
4. Resolve ticket: `POST /api/admin/support/tickets/{ticketNumber}/resolve`

### User Management
1. List users with filters: `GET /api/admin/users`
2. View user details: `GET /api/admin/users/{id}`
3. Block/unblock: `PUT /api/admin/users/{id}/toggle-active`
4. Make admin: `PUT /api/admin/users/{id}/role`

---

## Error Responses

Same format as customer portal. See CUSTOMER_PORTAL_API.md for details.
