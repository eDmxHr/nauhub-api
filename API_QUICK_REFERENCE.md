# API Quick Reference

Quick overview of key endpoints for both Customer and Admin portals.

---

## 🎬 Customer Portal Endpoints

### Authentication
- `POST /api/auth/send-otp` - Send OTP to phone
- `POST /api/auth/verify-otp` - Verify OTP and login/register
- `GET /api/auth/user` - Get current user
- `POST /api/auth/logout` - Logout

### Subscriptions
- `GET /api/subscriptions/plans` - View available plans (public)
- `POST /api/subscriptions/subscribe` - First time subscription
- `POST /api/subscriptions/renew` - Renew/extend subscription
- `POST /api/subscriptions/upgrade` - Upgrade to different plan
- `POST /api/subscriptions/request-refund` - Request refund
- `GET /api/subscriptions/status` - Check subscription status
- `GET /api/subscriptions/history` - View subscription history
- `GET /api/subscriptions/payments` - View payment history
- `POST /api/subscriptions/cancel` - Cancel subscription

### Profiles
- `GET /api/profiles` - List profiles
- `POST /api/profiles` - Create profile
- `PUT /api/profiles/{id}` - Update profile
- `DELETE /api/profiles/{id}` - Delete profile
- `POST /api/profiles/{id}/verify-pin` - Verify PIN

### Content Browsing (Public)
- `GET /api/videos` - Browse videos
- `GET /api/videos/{id}` - Video details
- `GET /api/videos/featured` - Featured content
- `GET /api/videos/trending` - Trending content
- `GET /api/categories` - List categories
- `GET /api/genres` - List genres

### Streaming (Auth Required)
- `GET /api/stream/{videoUid}` - Get streaming URL

### Watch History
- `GET /api/watch-history` - View history
- `GET /api/watch-history/continue-watching` - Continue watching
- `POST /api/watch-history/update-progress` - Update progress
- `POST /api/watch-history/resume-point` - Get resume point

### My List
- `GET /api/playlists/my-list` - Get My List
- `POST /api/playlists/my-list/add` - Add to My List
- `DELETE /api/playlists/my-list/remove` - Remove from My List

### Support Tickets
- `GET /api/support/categories` - Get categories
- `GET /api/support/tickets` - List my tickets
- `POST /api/support/tickets` - Create ticket
- `GET /api/support/tickets/{ticketNumber}` - View ticket
- `POST /api/support/tickets/{ticketNumber}/reply` - Reply to ticket
- `POST /api/support/tickets/{ticketNumber}/close` - Close ticket
- `POST /api/support/tickets/{ticketNumber}/reopen` - Reopen ticket

---

## 🛠️ Admin Portal Endpoints

### Dashboard
- `GET /api/admin/dashboard` - Dashboard stats
- `GET /api/admin/dashboard/revenue-chart` - Revenue chart data
- `GET /api/admin/dashboard/user-growth-chart` - User growth data

### User Management
- `GET /api/admin/users` - List users (with filters)
- `GET /api/admin/users/stats` - User statistics
- `GET /api/admin/users/{id}` - User details (comprehensive)
- `PUT /api/admin/users/{id}/role` - Change role
- `PUT /api/admin/users/{id}/toggle-active` - Block/unblock user

### Subscription Management
- `GET /api/admin/subscriptions/stats` - Subscription & payment stats
- `GET /api/admin/subscriptions/all` - All subscriptions
- `GET /api/admin/subscriptions/{id}` - Subscription details
- `POST /api/admin/subscriptions/{id}/extend` - Extend/renew subscription

### Payment Management
- `GET /api/admin/payments/pending` - Pending payments
- `GET /api/admin/payments/all` - All payments
- `GET /api/admin/payments/{id}` - Payment details (with proof URL)
- `POST /api/admin/payments/{id}/approve` - Approve payment
- `POST /api/admin/payments/{id}/reject` - Reject payment
- `POST /api/admin/payments/{id}/refund` - Process refund

### Content Management
- `POST /api/admin/categories` - Create category
- `POST /api/admin/genres` - Create genre
- `POST /api/admin/videos` - Create video
- `POST /api/admin/videos/{videoId}/seasons` - Create season
- `POST /api/admin/seasons/{seasonId}/episodes` - Create episode

### Video Upload
- `POST /api/admin/cloudflare/create-direct-upload-url` - Generate upload URL (TUS)
- `POST /api/admin/cloudflare/check-tus-upload-progress` - Check upload progress
- `POST /api/admin/cloudflare/upload-from-url` - Upload from URL
- `GET /api/admin/cloudflare/video/{videoUid}/status` - Check processing status
- `DELETE /api/admin/cloudflare/video/{videoUid}` - Delete video

### Subscription Plans
- `GET /api/admin/plans` - List plans
- `POST /api/admin/plans` - Create plan
- `PUT /api/admin/plans/{id}` - Update plan
- `DELETE /api/admin/plans/{id}` - Delete plan
- `PUT /api/admin/plans/{id}/toggle-active` - Enable/disable plan

### Support Tickets
- `GET /api/admin/support/stats` - Ticket statistics
- `GET /api/admin/support/tickets` - All tickets (with filters)
- `GET /api/admin/support/tickets/{ticketNumber}` - Ticket details
- `POST /api/admin/support/tickets/{ticketNumber}/assign` - Assign ticket
- `PUT /api/admin/support/tickets/{ticketNumber}/status` - Update status
- `PUT /api/admin/support/tickets/{ticketNumber}/priority` - Update priority
- `POST /api/admin/support/tickets/{ticketNumber}/reply` - Reply (customer/internal)
- `POST /api/admin/support/tickets/{ticketNumber}/resolve` - Resolve ticket
- `POST /api/admin/support/tickets/bulk-update` - Bulk operations

### Activity Logs
- `GET /api/admin/activity-logs` - View logs
- `GET /api/admin/activity-logs/stats` - Log statistics
- `GET /api/admin/activity-logs/critical` - Critical activities
- `DELETE /api/admin/activity-logs/cleanup` - Cleanup old logs

### Notifications
- `GET /api/admin/notifications` - List notifications
- `PUT /api/admin/notifications/mark-all-read` - Mark all as read
- `DELETE /api/admin/notifications/cleanup` - Cleanup old notifications

### IP Blocking
- `GET /api/admin/blocked-ips` - List blocked IPs
- `POST /api/admin/blocked-ips` - Block IP
- `DELETE /api/admin/blocked-ips/{id}` - Unblock IP
- `POST /api/admin/blocked-ips/cleanup` - Cleanup expired blocks

### System Configuration
- `GET /api/admin/config` - Get all configs
- `PUT /api/admin/config/{key}` - Update config
- `POST /api/admin/config/clear-cache` - Clear cache

---

## 🔑 Key Flows

### Customer Subscription Flow
1. Browse plans: `GET /api/subscriptions/plans`
2. Subscribe: `POST /api/subscriptions/subscribe` (upload payment proof)
3. Wait for approval
4. Start streaming: `GET /api/stream/{videoUid}`
5. Later renew: `POST /api/subscriptions/renew`
6. Request refund if needed: `POST /api/subscriptions/request-refund`

### Admin Payment Processing Flow
1. View pending: `GET /api/admin/payments/pending`
2. Check proof: `GET /api/admin/payments/{id}` (see proof_image_url)
3. Approve/reject: `POST /api/admin/payments/{id}/approve`
4. If refund needed: `POST /api/admin/payments/{id}/refund`

### Admin Subscription Extension Flow
1. View user: `GET /api/admin/users/{id}`
2. See subscription: View current_subscription in response
3. Extend: `POST /api/admin/subscriptions/{id}/extend`

### Support Ticket Flow (Customer)
1. Create ticket: `POST /api/support/tickets`
2. View tickets: `GET /api/support/tickets`
3. Reply: `POST /api/support/tickets/{ticketNumber}/reply`
4. Close: `POST /api/support/tickets/{ticketNumber}/close`

### Support Ticket Flow (Admin)
1. View unassigned: `GET /api/admin/support/tickets?assigned_to=unassigned`
2. Assign to self: `POST /api/admin/support/tickets/{ticketNumber}/assign`
3. Reply to customer: `POST /api/admin/support/tickets/{ticketNumber}/reply`
4. Resolve: `POST /api/admin/support/tickets/{ticketNumber}/resolve`

---

## 📝 Important Notes

### Authentication
- Customer: OTP-based (phone number)
- Admin: Email/password
- All protected endpoints require: `Authorization: Bearer {token}`

### Payment Proof
- Full URL available at: `http://domain.com/storage/{proof_image}`
- Run `php artisan storage:link` to enable access

### Subscription Renewal vs Upgrade
- **Renew**: Extend with same or different plan (starts after current expires)
- **Upgrade**: Switch plan immediately (replaces current subscription)

### Refund Process
- Customer: Submit request via `POST /api/subscriptions/request-refund`
- Creates billing support ticket
- Admin reviews and processes via `POST /api/admin/payments/{id}/refund`
- Supports full and partial refunds

### File Uploads
- Payment proofs: Max 5MB
- Support attachments: Max 5MB per file, max 5 files
- Videos: Use TUS protocol for files >200MB

### Rate Limits
- OTP: Max 5 attempts per IP per hour
- Failed login: Auto-block after 5 attempts (configurable)

---

## 🔗 Full Documentation

- **Customer Portal**: See `CUSTOMER_PORTAL_API.md`
- **Admin Portal**: See `ADMIN_PORTAL_API.md`
