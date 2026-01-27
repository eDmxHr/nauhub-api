# Customer Portal API Documentation

Complete API reference for the customer-facing portal (streaming platform).

---

## Table of Contents
1. [Authentication](#authentication)
2. [Profiles](#profiles)
3. [Content Browsing](#content-browsing)
4. [Streaming](#streaming)
5. [Watch History](#watch-history)
6. [Playlists (My List)](#playlists-my-list)
7. [Subscriptions](#subscriptions)
8. [Support Tickets](#support-tickets)

---

## Authentication

### Send OTP
**POST** `/api/auth/send-otp`

Send OTP to user's phone number for login/registration.

**Request:**
```json
{
  "phone": "7771234"
}
```

**Response:**
```json
{
  "message": "OTP sent successfully",
  "expires_in_seconds": 180
}
```

---

### Verify OTP
**POST** `/api/auth/verify-otp`

Verify OTP and login/register user.

**Request:**
```json
{
  "phone": "7771234",
  "otp": "123456"
}
```

**Response:**
```json
{
  "message": "Login successful",
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "Ahmed Hassan",
    "email": "ahmed@example.com",
    "phone": "7771234",
    "is_subscribed": true,
    "is_active": true
  }
}
```

---

### Get Current User
**GET** `/api/auth/user`

Get authenticated user details.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "id": 1,
  "name": "Ahmed Hassan",
  "email": "ahmed@example.com",
  "phone": "7771234",
  "is_subscribed": true,
  "is_active": true,
  "created_at": "2025-12-01T10:30:00.000000Z"
}
```

---

### Logout
**POST** `/api/auth/logout`

Logout and invalidate current token.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "message": "Logged out successfully"
}
```

---

## Profiles

### List Profiles
**GET** `/api/profiles`

Get all profiles for authenticated user.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "message": "Profiles retrieved successfully",
  "profiles": [
    {
      "id": 1,
      "user_id": 1,
      "name": "Ahmed",
      "avatar": "avatars/avatar1.png",
      "is_kids": false,
      "has_pin": true,
      "created_at": "2025-12-01T10:30:00.000000Z"
    }
  ]
}
```

---

### Create Profile
**POST** `/api/profiles`

Create a new profile.

**Request:**
```json
{
  "name": "Kids",
  "avatar": "avatar2.png",
  "is_kids": true,
  "pin": "1234"
}
```

**Response:**
```json
{
  "message": "Profile created successfully",
  "profile": {
    "id": 2,
    "user_id": 1,
    "name": "Kids",
    "avatar": "avatar2.png",
    "is_kids": true,
    "has_pin": true
  }
}
```

---

### Update Profile
**PUT** `/api/profiles/{id}`

Update profile details.

**Request:**
```json
{
  "name": "Ahmed (Updated)",
  "avatar": "avatar3.png",
  "pin": "5678"
}
```

---

### Delete Profile
**DELETE** `/api/profiles/{id}`

Delete a profile.

**Response:**
```json
{
  "message": "Profile deleted successfully"
}
```

---

### Verify Profile PIN
**POST** `/api/profiles/{id}/verify-pin`

Verify profile PIN before access.

**Request:**
```json
{
  "pin": "1234"
}
```

**Response:**
```json
{
  "message": "PIN verified successfully",
  "profile": { ... }
}
```

---

## Content Browsing

All browsing endpoints are **public** (no authentication required).

### List Videos
**GET** `/api/videos`

Get paginated list of videos with filters.

**Query Parameters:**
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15, max: 100)
- `search` - Search by title or description
- `category_id` - Filter by category
- `genre_id` - Filter by genre
- `type` - Filter by type: `movie` or `series`
- `sort_by` - Sort order: `newest`, `oldest`, `popular`, `trending`

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Inception",
      "description": "A mind-bending thriller...",
      "type": "movie",
      "thumbnail": "https://...",
      "video_uid": "abc123",
      "duration": 8880,
      "quality": "1080p",
      "release_date": "2010-07-16",
      "rating": 8.8,
      "views": 15420,
      "categories": [...],
      "genres": [...]
    }
  ],
  "pagination": {
    "total": 150,
    "per_page": 15,
    "current_page": 1,
    "last_page": 10
  }
}
```

---

### Get Video Details
**GET** `/api/videos/{id}`

Get detailed information about a single video.

**Response:**
```json
{
  "id": 1,
  "title": "Inception",
  "description": "...",
  "type": "series",
  "thumbnail": "...",
  "video_uid": "abc123",
  "duration": 8880,
  "quality": "1080p",
  "release_date": "2010-07-16",
  "rating": 8.8,
  "views": 15420,
  "categories": [...],
  "genres": [...],
  "seasons": [
    {
      "id": 1,
      "season_number": 1,
      "title": "Season 1",
      "episodes_count": 10
    }
  ]
}
```

---

### Featured Videos
**GET** `/api/videos/featured`

Get featured videos for homepage.

---

### Trending Videos
**GET** `/api/videos/trending`

Get trending videos based on recent views.

---

### List Categories
**GET** `/api/categories`

Get all categories.

---

### List Genres
**GET** `/api/genres`

Get all genres.

---

### Get Seasons
**GET** `/api/videos/{videoId}/seasons`

Get all seasons for a TV series.

---

### Get Episodes
**GET** `/api/seasons/{seasonId}/episodes`

Get all episodes for a season.

---

## Streaming

**Authentication Required**

### Get Stream URL
**GET** `/api/stream/{videoUid}`

Get signed streaming URLs for a video.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "video_uid": "abc123",
  "stream_url": "https://customer-xxx.cloudflarestream.com/abc123/manifest/video.m3u8?token=...",
  "embed_url": "https://customer-xxx.cloudflarestream.com/abc123/iframe?token=...",
  "thumbnail": "https://customer-xxx.cloudflarestream.com/abc123/thumbnails/thumbnail.jpg",
  "duration": 8880,
  "quality": "1080p"
}
```

---

### Increment View Count
**POST** `/api/videos/{id}/view`

Increment view count for a video.

**Headers:** `Authorization: Bearer {token}`

---

## Watch History

**Authentication Required**

### Get Watch History
**GET** `/api/watch-history`

Get watch history for current profile.

**Query Parameters:**
- `profile_id` - Profile ID

**Response:**
```json
{
  "watch_history": [
    {
      "id": 1,
      "video": { ... },
      "episode": { ... },
      "progress_seconds": 1200,
      "duration_seconds": 3600,
      "completed": false,
      "last_watched_at": "2025-12-15T20:30:00.000000Z"
    }
  ]
}
```

---

### Continue Watching
**GET** `/api/watch-history/continue-watching`

Get videos/episodes to continue watching.

**Query Parameters:**
- `profile_id` - Profile ID

---

### Update Progress
**POST** `/api/watch-history/update-progress`

Update playback progress.

**Request:**
```json
{
  "profile_id": 1,
  "video_id": 5,
  "episode_id": null,
  "progress_seconds": 1800,
  "duration_seconds": 3600
}
```

---

### Get Resume Point
**POST** `/api/watch-history/resume-point`

Get resume point for a video/episode.

**Request:**
```json
{
  "profile_id": 1,
  "video_id": 5
}
```

**Response:**
```json
{
  "resume_seconds": 1800,
  "duration_seconds": 3600
}
```

---

### Mark as Completed
**POST** `/api/watch-history/mark-completed`

Mark video/episode as completed.

**Request:**
```json
{
  "profile_id": 1,
  "video_id": 5
}
```

---

### Remove from History
**DELETE** `/api/watch-history/remove`

Remove item from watch history.

**Request:**
```json
{
  "profile_id": 1,
  "video_id": 5
}
```

---

### Clear All History
**DELETE** `/api/watch-history/clear-all`

Clear entire watch history for profile.

**Request:**
```json
{
  "profile_id": 1
}
```

---

## Playlists (My List)

**Authentication Required**

### Get My List
**GET** `/api/playlists/my-list`

Get "My List" playlist for profile.

**Query Parameters:**
- `profile_id` - Profile ID

**Response:**
```json
{
  "playlist": {
    "id": 1,
    "name": "My List",
    "videos": [...]
  }
}
```

---

### Add to My List
**POST** `/api/playlists/my-list/add`

Add video to My List.

**Request:**
```json
{
  "profile_id": 1,
  "video_id": 5
}
```

---

### Remove from My List
**DELETE** `/api/playlists/my-list/remove`

Remove video from My List.

**Request:**
```json
{
  "profile_id": 1,
  "video_id": 5
}
```

---

### Check if in My List
**POST** `/api/playlists/my-list/check`

Check if video is in My List.

**Request:**
```json
{
  "profile_id": 1,
  "video_id": 5
}
```

**Response:**
```json
{
  "in_my_list": true
}
```

---

## Subscriptions

### Get Plans (Public)
**GET** `/api/subscriptions/plans`

Get all available subscription plans.

**Response:**
```json
{
  "plans": [
    {
      "id": "premium_monthly",
      "tier": "premium",
      "name": "Premium Monthly",
      "duration": "monthly",
      "price": 250.00,
      "currency": "MVR",
      "duration_days": 30,
      "features": ["4K Streaming", "5 Profiles", "Download"],
      "popular": true,
      "savings": "Save 20%",
      "discount_text": "Best Value!"
    }
  ]
}
```

---

### Get Subscription Status
**GET** `/api/subscriptions/status`

Get current subscription status.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "subscribed": true,
  "subscription": {
    "id": 1,
    "plan_type": "monthly",
    "amount": 250.00,
    "start_date": "2026-01-01",
    "end_date": "2026-01-31",
    "status": "active"
  },
  "days_remaining": 15
}
```

---

### Subscribe (First Time)
**POST** `/api/subscriptions/subscribe`

Subscribe to a plan for the first time.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "plan_id": "premium_monthly",
  "proof_image": "<file upload>"
}
```

**Response:**
```json
{
  "message": "Subscription created. Payment is pending verification.",
  "subscription": { ... },
  "payment": {
    "id": 1,
    "reference_code": "PAY-ABC123",
    "amount": 250.00,
    "status": "pending"
  }
}
```

---

### Renew Subscription
**POST** `/api/subscriptions/renew`

Renew/extend existing subscription.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "plan_id": "premium_monthly",
  "proof_image": "<file upload>"
}
```

**Response:**
```json
{
  "message": "Renewal request submitted successfully.",
  "subscription": { ... },
  "payment": { ... },
  "note": "Your new subscription will start after your current subscription ends."
}
```

---

### Upgrade Subscription
**POST** `/api/subscriptions/upgrade`

Upgrade to a different plan (e.g., monthly → annual).

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "plan_id": "premium_annual",
  "proof_image": "<file upload>"
}
```

**Response:**
```json
{
  "message": "Upgrade request submitted successfully.",
  "subscription": { ... },
  "payment": { ... },
  "note": "Your current subscription will be replaced when the upgrade is approved.",
  "current_subscription": { ... }
}
```

---

### Get Subscription History
**GET** `/api/subscriptions/history`

Get all past and current subscriptions.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "message": "Subscription history retrieved successfully",
  "subscriptions": [
    {
      "id": 1,
      "plan_type": "monthly",
      "amount": 250.00,
      "start_date": "2026-01-01",
      "end_date": "2026-02-01",
      "status": "active",
      "created_at": "2026-01-01T10:00:00.000000Z"
    }
  ]
}
```

---

### Get Payment History
**GET** `/api/subscriptions/payments`

Get all payment transactions with detailed information.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "message": "Payment history retrieved successfully",
  "payments": [
    {
      "id": 1,
      "reference_code": "PAY-ABC123XYZ",
      "amount": 250.00,
      "currency": "MVR",
      "status": "approved",
      "refund_status": "none",
      "refunded_amount": null,
      "subscription": {
        "id": 1,
        "plan_type": "monthly",
        "start_date": "2026-01-01",
        "end_date": "2026-02-01",
        "status": "active"
      },
      "created_at": "2026-01-01 10:00:00",
      "verified_at": "2026-01-01 11:30:00"
    },
    {
      "id": 2,
      "reference_code": "PAY-DEF456GHI",
      "amount": 500.00,
      "currency": "MVR",
      "status": "approved",
      "refund_status": "refunded",
      "refunded_amount": 500.00,
      "subscription": {
        "id": 2,
        "plan_type": "monthly",
        "start_date": "2025-12-01",
        "end_date": "2026-01-01",
        "status": "cancelled"
      },
      "created_at": "2025-12-01 09:00:00",
      "verified_at": "2025-12-01 10:15:00"
    }
  ]
}
```

**Payment Status Values:**
- `pending` - Awaiting admin approval
- `approved` - Payment approved and subscription activated
- `rejected` - Payment rejected by admin

**Refund Status Values:**
- `none` - No refund
- `partially_refunded` - Partial refund processed
- `refunded` - Full refund processed

---

### Request Refund
**POST** `/api/subscriptions/request-refund`

Submit a refund request for a payment (creates a support ticket).

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "payment_id": 1,
  "reason": "Service not as expected. Requesting refund for this month's payment."
}
```

**Response:**
```json
{
  "message": "Refund request submitted successfully. Our team will review and process your request.",
  "ticket": {
    "ticket_number": "TKT-2026-00015",
    "status": "open",
    "subject": "Refund Request - Payment PAY-ABC123",
    "created_at": "2026-01-11T10:00:00.000000Z"
  },
  "note": "You can track the status of your refund request in the support tickets section."
}
```

**Notes:**
- Only approved payments can be refunded
- Cannot request refund for already refunded payments
- Prevents duplicate refund requests for the same payment
- Creates a high-priority billing support ticket
- Admin will review and process the refund request

---

### Cancel Subscription
**POST** `/api/subscriptions/cancel`

Cancel active subscription (subscription remains active until end date).

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "message": "Subscription cancelled successfully",
  "subscription": { ... }
}
```

---

## Support Tickets

**Authentication Required**

### Get Ticket Categories
**GET** `/api/support/categories`

Get available ticket categories and priorities.

**Response:**
```json
{
  "categories": {
    "movie_request": "Movie Request",
    "series_request": "Series Request",
    "technical_issue": "Technical Issue",
    "billing": "Billing & Payments",
    "account": "Account Issues",
    "feedback": "Feedback",
    "other": "Other"
  },
  "priorities": {
    "low": "Low",
    "medium": "Medium",
    "high": "High",
    "urgent": "Urgent"
  }
}
```

---

### List My Tickets
**GET** `/api/support/tickets`

Get all tickets for authenticated user.

**Query Parameters:**
- `status` - Filter by status: `open`, `in_progress`, `awaiting_reply`, `resolved`, `closed`
- `category` - Filter by category

**Response:**
```json
{
  "tickets": [
    {
      "id": 1,
      "ticket_number": "TKT-2026-00001",
      "category": "movie_request",
      "priority": "medium",
      "subject": "Request: Interstellar",
      "description": "Can you add Interstellar movie?",
      "status": "open",
      "created_at": "2026-01-10T10:00:00.000000Z",
      "last_activity_at": "2026-01-10T10:00:00.000000Z"
    }
  ],
  "pagination": { ... }
}
```

---

### Create Ticket
**POST** `/api/support/tickets`

Create a new support ticket.

**Request:**
```json
{
  "category": "movie_request",
  "subject": "Request: Interstellar",
  "description": "Can you please add the movie Interstellar to the platform?",
  "priority": "medium",
  "attachments": ["<file upload>", "<file upload>"]
}
```

**Response:**
```json
{
  "message": "Support ticket created successfully",
  "ticket": {
    "id": 1,
    "ticket_number": "TKT-2026-00001",
    "category": "movie_request",
    "status": "open",
    "subject": "Request: Interstellar",
    "description": "...",
    "created_at": "2026-01-10T10:00:00.000000Z"
  }
}
```

---

### Get Ticket Details
**GET** `/api/support/tickets/{ticketNumber}`

Get ticket details with conversation history.

**Response:**
```json
{
  "ticket": {
    "id": 1,
    "ticket_number": "TKT-2026-00001",
    "category": "movie_request",
    "status": "in_progress",
    "subject": "Request: Interstellar",
    "replies": [
      {
        "id": 1,
        "message": "We'll check availability and add it soon!",
        "is_admin_reply": true,
        "user": {
          "id": 2,
          "name": "Support Team"
        },
        "created_at": "2026-01-10T11:00:00.000000Z"
      }
    ],
    "assigned_to": {
      "id": 2,
      "name": "Admin User"
    }
  }
}
```

---

### Reply to Ticket
**POST** `/api/support/tickets/{ticketNumber}/reply`

Add a reply to a ticket.

**Request:**
```json
{
  "message": "Thank you! Looking forward to it.",
  "attachments": ["<file upload>"]
}
```

---

### Close Ticket
**POST** `/api/support/tickets/{ticketNumber}/close`

Close a ticket.

**Response:**
```json
{
  "message": "Ticket closed successfully",
  "ticket": { ... }
}
```

---

### Reopen Ticket
**POST** `/api/support/tickets/{ticketNumber}/reopen`

Reopen a resolved or closed ticket.

**Request:**
```json
{
  "reason": "Issue not resolved. Still experiencing the problem."
}
```

**Response:**
```json
{
  "message": "Ticket reopened successfully",
  "ticket": { ... }
}
```

---

## Common Response Codes

- **200 OK** - Success
- **201 Created** - Resource created successfully
- **400 Bad Request** - Invalid request data
- **401 Unauthorized** - Missing or invalid authentication token
- **403 Forbidden** - User doesn't have permission
- **404 Not Found** - Resource not found
- **422 Unprocessable Entity** - Validation error
- **500 Internal Server Error** - Server error

---

## Error Response Format

```json
{
  "message": "Error description",
  "errors": {
    "field_name": ["Error message for this field"]
  }
}
```
