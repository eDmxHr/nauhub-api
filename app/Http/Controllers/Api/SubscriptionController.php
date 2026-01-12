<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    /**
     * Get available subscription plans with pricing.
     */
    public function plans()
    {
        // Fetch active plans from database
        $plans = SubscriptionPlan::active()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->plan_id,
                    'tier' => $plan->tier,
                    'name' => $plan->name,
                    'duration' => $plan->duration,
                    'price' => (float) $plan->price,
                    'currency' => $plan->currency,
                    'duration_days' => $plan->duration_days,
                    'features' => $plan->features,
                    'popular' => $plan->is_popular,
                    'savings' => $plan->savings_text,
                    'discount_text' => $plan->discount_text,
                ];
            });

        return response()->json([
            'message' => 'Subscription plans retrieved successfully',
            'plans' => $plans,
        ]);
    }

    /**
     * Get current user's subscription status.
     */
    public function status(Request $request)
    {
        $userId = $request->user()->id;

        $subscription = Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->orderBy('end_date', 'desc')
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription',
                'subscribed' => false,
                'subscription' => null,
            ]);
        }

        return response()->json([
            'message' => 'Subscription retrieved successfully',
            'subscribed' => true,
            'subscription' => $subscription,
            'days_remaining' => now()->diffInDays($subscription->end_date),
        ]);
    }

    /**
     * Create a new subscription (submit payment).
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|string|exists:subscription_plans,plan_id',
            'proof_image' => 'required|image|max:5120', // 5MB max
        ]);

        $userId = $request->user()->id;

        // Fetch plan from database
        $planModel = SubscriptionPlan::where('plan_id', $validated['plan_id'])->first();

        if (!$planModel) {
            return response()->json([
                'message' => 'Invalid subscription plan'
            ], 400);
        }

        if (!$planModel->is_active) {
            return response()->json([
                'message' => 'This subscription plan is no longer available'
            ], 400);
        }

        // Map to plan array format
        $plan = [
            'tier' => $planModel->tier,
            'duration' => $planModel->duration,
            'amount' => $planModel->price,
            'days' => $planModel->duration_days,
        ];

        // Upload payment proof
        $proofPath = null;
        if ($request->hasFile('proof_image')) {
            $proofPath = $request->file('proof_image')->store('payment_proofs', 'public');
        }

        // Create subscription (pending until payment approved)
        $subscription = Subscription::create([
            'user_id' => $userId,
            'plan_type' => $plan['duration'], // monthly or annual
            'amount' => $plan['amount'],
            'currency' => 'MVR',
            'start_date' => now(),
            'end_date' => now()->addDays($plan['days']),
            'status' => 'pending',
            'auto_renew' => false,
        ]);

        // Create payment record
        $payment = Payment::create([
            'user_id' => $userId,
            'subscription_id' => $subscription->id,
            'amount' => $plan['amount'],
            'currency' => 'MVR',
            'reference_code' => 'PAY-' . strtoupper(Str::random(10)),
            'proof_image' => $proofPath,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Subscription created. Payment is pending verification.',
            'subscription' => $subscription,
            'payment' => $payment,
        ], 201);
    }

    /**
     * Get user's subscription history.
     */
    public function history(Request $request)
    {
        $userId = $request->user()->id;

        $subscriptions = Subscription::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Subscription history retrieved successfully',
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Get user's payment history.
     *
     * GET /api/subscriptions/payments
     */
    public function payments(Request $request)
    {
        $userId = $request->user()->id;

        $payments = Payment::where('user_id', $userId)
            ->with(['subscription'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'reference_code' => $payment->reference_code,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'refund_status' => $payment->refund_status,
                    'refunded_amount' => $payment->refunded_amount ? (float) $payment->refunded_amount : null,
                    'subscription' => $payment->subscription ? [
                        'id' => $payment->subscription->id,
                        'plan_type' => $payment->subscription->plan_type,
                        'start_date' => $payment->subscription->start_date->format('Y-m-d'),
                        'end_date' => $payment->subscription->end_date->format('Y-m-d'),
                        'status' => $payment->subscription->status,
                    ] : null,
                    'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                    'verified_at' => $payment->verified_at ? $payment->verified_at->format('Y-m-d H:i:s') : null,
                ];
            });

        return response()->json([
            'message' => 'Payment history retrieved successfully',
            'payments' => $payments,
        ]);
    }

    /**
     * Request a refund for a payment.
     * Creates a support ticket for refund request.
     *
     * POST /api/subscriptions/request-refund
     */
    public function requestRefund(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|integer|exists:payments,id',
            'reason' => 'required|string|max:1000',
        ]);

        $userId = $request->user()->id;

        // Get the payment
        $payment = Payment::with(['subscription', 'user'])->find($validated['payment_id']);

        // Verify payment belongs to user
        if ($payment->user_id !== $userId) {
            return response()->json([
                'message' => 'Payment not found or does not belong to you',
            ], 403);
        }

        // Check if payment can be refunded
        if ($payment->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved payments can be refunded',
            ], 400);
        }

        if ($payment->refund_status === 'refunded') {
            return response()->json([
                'message' => 'This payment has already been refunded',
            ], 400);
        }

        // Check if there's already a pending refund request
        $existingTicket = \App\Models\SupportTicket::where('user_id', $userId)
            ->where('category', 'billing')
            ->where('subject', 'like', "%Refund Request - Payment {$payment->reference_code}%")
            ->whereIn('status', ['open', 'in_progress', 'awaiting_reply'])
            ->first();

        if ($existingTicket) {
            return response()->json([
                'message' => 'You already have a pending refund request for this payment',
                'ticket_number' => $existingTicket->ticket_number,
            ], 400);
        }

        // Create support ticket for refund request
        $ticket = \App\Models\SupportTicket::create([
            'ticket_number' => \App\Models\SupportTicket::generateTicketNumber(),
            'user_id' => $userId,
            'category' => 'billing',
            'priority' => 'high',
            'subject' => "Refund Request - Payment {$payment->reference_code}",
            'description' => "Customer is requesting a refund for payment {$payment->reference_code}.\n\n"
                . "Payment Details:\n"
                . "- Amount: {$payment->amount} {$payment->currency}\n"
                . "- Date: {$payment->created_at->format('Y-m-d H:i:s')}\n"
                . "- Subscription: {$payment->subscription->plan_type}\n\n"
                . "Reason for refund:\n{$validated['reason']}",
            'status' => 'open',
            'last_activity_at' => now(),
        ]);

        // Log the refund request
        ActivityLog::log(
            'refund_requested',
            "User {$request->user()->name} requested refund for payment #{$payment->id}",
            'Payment',
            $payment->id,
            [
                'payment_id' => $payment->id,
                'reference_code' => $payment->reference_code,
                'amount' => $payment->amount,
                'reason' => $validated['reason'],
                'ticket_number' => $ticket->ticket_number,
                'user_id' => $userId,
            ],
            'info'
        );

        return response()->json([
            'message' => 'Refund request submitted successfully. Our team will review and process your request.',
            'ticket' => [
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'subject' => $ticket->subject,
                'created_at' => $ticket->created_at,
            ],
            'note' => 'You can track the status of your refund request in the support tickets section.',
        ], 201);
    }

    /**
     * Cancel active subscription.
     */
    public function cancel(Request $request)
    {
        $userId = $request->user()->id;

        $subscription = Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->orderBy('end_date', 'desc')
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription to cancel',
            ], 404);
        }

        $subscription->update([
            'status' => 'cancelled',
            'auto_renew' => false,
        ]);

        // Check if user has any other active subscriptions
        $hasOtherActiveSubscription = Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->where('id', '!=', $subscription->id)
            ->exists();

        // Update user's is_subscribed status only if no other active subscriptions
        if (!$hasOtherActiveSubscription) {
            $request->user()->update([
                'is_subscribed' => false,
            ]);
        }

        return response()->json([
            'message' => 'Subscription cancelled successfully',
            'subscription' => $subscription,
        ]);
    }

    /**
     * Renew/extend current subscription by submitting payment proof.
     * User submits payment for same plan or different plan.
     *
     * POST /api/subscriptions/renew
     */
    public function renew(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|string|exists:subscription_plans,plan_id',
            'proof_image' => 'required|image|max:5120', // 5MB max
        ]);

        $userId = $request->user()->id;

        // Get the plan
        $planModel = SubscriptionPlan::where('plan_id', $validated['plan_id'])->first();

        if (!$planModel || !$planModel->is_active) {
            return response()->json([
                'message' => 'Invalid or inactive subscription plan'
            ], 400);
        }

        // Get current active subscription (if any)
        $currentSubscription = Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->orderBy('end_date', 'desc')
            ->first();

        // Calculate start and end dates
        // If user has active subscription, extend from current end date
        // Otherwise, start from today
        $startDate = $currentSubscription ? $currentSubscription->end_date->copy()->addDay() : now();
        $endDate = $startDate->copy()->addDays($planModel->duration_days);

        // Upload payment proof
        $proofPath = null;
        if ($request->hasFile('proof_image')) {
            $proofPath = $request->file('proof_image')->store('payment_proofs', 'public');
        }

        // Create new subscription (pending until payment approved)
        $subscription = Subscription::create([
            'user_id' => $userId,
            'plan_type' => $planModel->duration,
            'amount' => $planModel->price,
            'currency' => 'MVR',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'pending',
            'auto_renew' => false,
        ]);

        // Create payment record
        $payment = Payment::create([
            'user_id' => $userId,
            'subscription_id' => $subscription->id,
            'amount' => $planModel->price,
            'currency' => 'MVR',
            'reference_code' => 'PAY-' . strtoupper(Str::random(10)),
            'proof_image' => $proofPath,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Renewal request submitted successfully. Payment is pending verification.',
            'subscription' => $subscription,
            'payment' => $payment,
            'note' => $currentSubscription
                ? 'Your new subscription will start after your current subscription ends.'
                : 'Your subscription will start immediately after payment approval.',
        ], 201);
    }

    /**
     * Upgrade current subscription to a different plan.
     * Upgrades take effect immediately after approval.
     *
     * POST /api/subscriptions/upgrade
     */
    public function upgrade(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|string|exists:subscription_plans,plan_id',
            'proof_image' => 'required|image|max:5120',
        ]);

        $userId = $request->user()->id;

        // Get current active subscription
        $currentSubscription = Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->orderBy('end_date', 'desc')
            ->first();

        if (!$currentSubscription) {
            return response()->json([
                'message' => 'No active subscription found. Please use /subscribe endpoint instead.',
            ], 400);
        }

        // Get the new plan
        $planModel = SubscriptionPlan::where('plan_id', $validated['plan_id'])->first();

        if (!$planModel || !$planModel->is_active) {
            return response()->json([
                'message' => 'Invalid or inactive subscription plan'
            ], 400);
        }

        // Check if it's actually an upgrade (different plan)
        if ($planModel->duration === $currentSubscription->plan_type) {
            return response()->json([
                'message' => 'You are already on this plan. Use /renew endpoint to extend your subscription.',
            ], 400);
        }

        // Upload payment proof
        $proofPath = null;
        if ($request->hasFile('proof_image')) {
            $proofPath = $request->file('proof_image')->store('payment_proofs', 'public');
        }

        // Create new subscription (will replace current one after approval)
        $subscription = Subscription::create([
            'user_id' => $userId,
            'plan_type' => $planModel->duration,
            'amount' => $planModel->price,
            'currency' => 'MVR',
            'start_date' => now(),
            'end_date' => now()->addDays($planModel->duration_days),
            'status' => 'pending',
            'auto_renew' => false,
        ]);

        // Create payment record
        $payment = Payment::create([
            'user_id' => $userId,
            'subscription_id' => $subscription->id,
            'amount' => $planModel->price,
            'currency' => 'MVR',
            'reference_code' => 'PAY-' . strtoupper(Str::random(10)),
            'proof_image' => $proofPath,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Upgrade request submitted successfully. Payment is pending verification.',
            'subscription' => $subscription,
            'payment' => $payment,
            'note' => 'Your current subscription will be replaced when the upgrade is approved.',
            'current_subscription' => $currentSubscription,
        ], 201);
    }

    // ============ ADMIN ENDPOINTS ============

    /**
     * Get all pending payments (Admin only).
     */
    public function pendingPayments()
    {
        $payments = Payment::with(['user', 'subscription'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Pending payments retrieved successfully',
            'payments' => $payments,
        ]);
    }

    /**
     * Approve a payment (Admin only).
     */
    public function approvePayment(Request $request, $paymentId)
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string',
        ]);

        $payment = Payment::with('subscription')->find($paymentId);

        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found',
            ], 404);
        }

        if ($payment->status !== 'pending') {
            return response()->json([
                'message' => 'Payment already processed',
            ], 400);
        }

        // Update payment
        $payment->update([
            'status' => 'approved',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        // Activate subscription
        if ($payment->subscription) {
            $payment->subscription->update([
                'status' => 'active',
            ]);

            // Update user's is_subscribed status
            $payment->user->update([
                'is_subscribed' => true,
            ]);
        }

        // Log payment approval
        ActivityLog::log(
            'payment_approved',
            "Payment #{$payment->id} approved for user {$payment->user->name}",
            'Payment',
            $payment->id,
            [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reference_code' => $payment->reference_code,
                'admin_notes' => $validated['admin_notes'] ?? null,
                'user_id' => $payment->user_id,
            ],
            'info'
        );

        return response()->json([
            'message' => 'Payment approved and subscription activated',
            'payment' => $payment->fresh(['subscription']),
        ]);
    }

    /**
     * Reject a payment (Admin only).
     */
    public function rejectPayment(Request $request, $paymentId)
    {
        $validated = $request->validate([
            'admin_notes' => 'required|string',
        ]);

        $payment = Payment::with('subscription')->find($paymentId);

        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found',
            ], 404);
        }

        if ($payment->status !== 'pending') {
            return response()->json([
                'message' => 'Payment already processed',
            ], 400);
        }

        // Update payment
        $payment->update([
            'status' => 'rejected',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'admin_notes' => $validated['admin_notes'],
        ]);

        // Cancel subscription
        if ($payment->subscription) {
            $payment->subscription->update([
                'status' => 'cancelled',
            ]);

            // Check if user has any other active subscriptions
            $hasOtherActiveSubscription = Subscription::where('user_id', $payment->user_id)
                ->where('status', 'active')
                ->where('end_date', '>=', now())
                ->where('id', '!=', $payment->subscription_id)
                ->exists();

            // Update user's is_subscribed status only if no other active subscriptions
            if (!$hasOtherActiveSubscription) {
                $payment->user->update([
                    'is_subscribed' => false,
                ]);
            }
        }

        // Log payment rejection
        ActivityLog::log(
            'payment_rejected',
            "Payment #{$payment->id} rejected for user {$payment->user->name}",
            'Payment',
            $payment->id,
            [
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reference_code' => $payment->reference_code,
                'admin_notes' => $validated['admin_notes'],
                'user_id' => $payment->user_id,
            ],
            'warning'
        );

        return response()->json([
            'message' => 'Payment rejected',
            'payment' => $payment->fresh(['subscription']),
        ]);
    }

    /**
     * Get all subscriptions (Admin only).
     */
    public function allSubscriptions(Request $request)
    {
        $status = $request->input('status'); // active, pending, expired, cancelled

        $query = Subscription::with('user')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $subscriptions = $query->paginate(20);

        return response()->json([
            'message' => 'Subscriptions retrieved successfully',
            'subscriptions' => $subscriptions->items(),
            'pagination' => [
                'total' => $subscriptions->total(),
                'per_page' => $subscriptions->perPage(),
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
            ],
        ]);
    }

    /**
     * Get all payments (Admin only).
     */
    public function allPayments(Request $request)
    {
        $status = $request->input('status'); // pending, approved, rejected

        $query = Payment::with(['user', 'subscription'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $payments = $query->paginate(20);

        return response()->json([
            'message' => 'Payments retrieved successfully',
            'payments' => $payments->items(),
            'pagination' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }

    /**
     * Extend/Renew a subscription (Admin only).
     * Can extend an existing active subscription or renew an expired one.
     *
     * POST /api/admin/subscriptions/{subscriptionId}/extend
     */
    public function extendSubscription(Request $request, $subscriptionId)
    {
        $validated = $request->validate([
            'days' => 'required_without:plan_id|integer|min:1|max:365',
            'plan_id' => 'required_without:days|string|exists:subscription_plans,plan_id',
            'reason' => 'required|string|max:500',
            'is_free' => 'nullable|boolean', // Free extension (e.g., compensation)
        ]);

        $subscription = Subscription::with('user')->find($subscriptionId);

        if (!$subscription) {
            return response()->json([
                'message' => 'Subscription not found',
            ], 404);
        }

        $isFree = $validated['is_free'] ?? false;
        $oldEndDate = $subscription->end_date;
        $daysToAdd = 0;
        $amount = 0;

        // If plan_id is provided, get days from plan
        if (isset($validated['plan_id'])) {
            $plan = SubscriptionPlan::where('plan_id', $validated['plan_id'])->first();
            $daysToAdd = $plan->duration_days;
            $amount = $isFree ? 0 : $plan->price;
        } else {
            $daysToAdd = $validated['days'];
        }

        // Calculate new end date
        // If subscription is expired, start from today; otherwise, extend from current end date
        $baseDate = $subscription->end_date->isPast() ? now() : $subscription->end_date;
        $newEndDate = $baseDate->copy()->addDays($daysToAdd);

        // Update subscription
        $subscription->update([
            'end_date' => $newEndDate,
            'status' => 'active',
        ]);

        // Update user's is_subscribed status
        $subscription->user->update([
            'is_subscribed' => true,
        ]);

        // Log the extension
        ActivityLog::log(
            'subscription_extended',
            "Subscription #{$subscription->id} extended by {$daysToAdd} days for user {$subscription->user->name}",
            'Subscription',
            $subscription->id,
            [
                'old_end_date' => $oldEndDate->format('Y-m-d'),
                'new_end_date' => $newEndDate->format('Y-m-d'),
                'days_added' => $daysToAdd,
                'reason' => $validated['reason'],
                'is_free' => $isFree,
                'extended_by' => $request->user()->id,
                'user_id' => $subscription->user_id,
            ],
            'info'
        );

        return response()->json([
            'message' => 'Subscription extended successfully',
            'subscription' => $subscription->fresh(),
            'extension_details' => [
                'old_end_date' => $oldEndDate->format('Y-m-d'),
                'new_end_date' => $newEndDate->format('Y-m-d'),
                'days_added' => $daysToAdd,
                'is_free' => $isFree,
            ],
        ]);
    }

    /**
     * Process a refund for a payment (Admin only).
     *
     * POST /api/admin/payments/{paymentId}/refund
     */
    public function refundPayment(Request $request, $paymentId)
    {
        $validated = $request->validate([
            'refund_amount' => 'required|numeric|min:0.01',
            'refund_type' => 'required|in:full,partial',
            'reason' => 'required|string|max:500',
            'cancel_subscription' => 'nullable|boolean', // Whether to cancel related subscription
        ]);

        $payment = Payment::with(['user', 'subscription'])->find($paymentId);

        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found',
            ], 404);
        }

        if ($payment->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved payments can be refunded',
            ], 400);
        }

        if ($payment->refund_status === 'refunded') {
            return response()->json([
                'message' => 'Payment has already been refunded',
            ], 400);
        }

        $refundAmount = $validated['refund_amount'];

        // Validate refund amount doesn't exceed payment amount
        $alreadyRefunded = $payment->refunded_amount ?? 0;
        $maxRefundable = $payment->amount - $alreadyRefunded;

        if ($refundAmount > $maxRefundable) {
            return response()->json([
                'message' => "Refund amount exceeds maximum refundable amount ({$maxRefundable} {$payment->currency})",
            ], 400);
        }

        // Determine refund status
        $totalRefunded = $alreadyRefunded + $refundAmount;
        $refundStatus = $totalRefunded >= $payment->amount ? 'refunded' : 'partially_refunded';

        // Update payment with refund info
        $payment->update([
            'refund_status' => $refundStatus,
            'refunded_amount' => $totalRefunded,
            'refund_reason' => $validated['reason'],
            'refunded_at' => now(),
            'refunded_by' => $request->user()->id,
        ]);

        // Cancel subscription if requested
        $cancelSubscription = $validated['cancel_subscription'] ?? false;
        if ($cancelSubscription && $payment->subscription) {
            $payment->subscription->update([
                'status' => 'cancelled',
            ]);

            // Check if user has any other active subscriptions
            $hasOtherActiveSubscription = Subscription::where('user_id', $payment->user_id)
                ->where('status', 'active')
                ->where('end_date', '>=', now())
                ->where('id', '!=', $payment->subscription_id)
                ->exists();

            if (!$hasOtherActiveSubscription) {
                $payment->user->update([
                    'is_subscribed' => false,
                ]);
            }
        }

        // Log the refund
        ActivityLog::log(
            'payment_refunded',
            "Payment #{$payment->id} refunded ({$refundAmount} {$payment->currency}) for user {$payment->user->name}",
            'Payment',
            $payment->id,
            [
                'refund_amount' => $refundAmount,
                'refund_type' => $validated['refund_type'],
                'total_refunded' => $totalRefunded,
                'refund_status' => $refundStatus,
                'reason' => $validated['reason'],
                'subscription_cancelled' => $cancelSubscription,
                'refunded_by' => $request->user()->id,
                'user_id' => $payment->user_id,
            ],
            'warning'
        );

        return response()->json([
            'message' => 'Refund processed successfully',
            'payment' => $payment->fresh(['subscription']),
            'refund_details' => [
                'refund_amount' => $refundAmount,
                'total_refunded' => $totalRefunded,
                'refund_status' => $refundStatus,
                'subscription_cancelled' => $cancelSubscription,
            ],
        ]);
    }

    /**
     * Get a single subscription with full details (Admin only).
     *
     * GET /api/admin/subscriptions/{subscriptionId}
     */
    public function showSubscription($subscriptionId)
    {
        $subscription = Subscription::with([
            'user:id,name,email,phone,is_subscribed',
        ])->find($subscriptionId);

        if (!$subscription) {
            return response()->json([
                'message' => 'Subscription not found',
            ], 404);
        }

        // Get related payments
        $payments = Payment::where('subscription_id', $subscriptionId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get plan details
        $plan = SubscriptionPlan::where('duration', $subscription->plan_type)
            ->where('is_active', true)
            ->first();

        return response()->json([
            'message' => 'Subscription retrieved successfully',
            'subscription' => $subscription,
            'payments' => $payments,
            'plan_details' => $plan ? [
                'plan_id' => $plan->plan_id,
                'name' => $plan->name,
                'tier' => $plan->tier,
                'price' => (float) $plan->price,
                'features' => $plan->features,
            ] : null,
            'days_remaining' => $subscription->end_date->isPast() ? 0 : now()->diffInDays($subscription->end_date),
            'is_expired' => $subscription->end_date->isPast(),
        ]);
    }

    /**
     * Get a single payment with full details (Admin only).
     *
     * GET /api/admin/payments/{paymentId}
     */
    public function showPayment($paymentId)
    {
        $payment = Payment::with([
            'user:id,name,email,phone',
            'subscription',
            'verifiedBy:id,name',
        ])->find($paymentId);

        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found',
            ], 404);
        }

        // Add full proof image URL
        $paymentData = $payment->toArray();
        if ($payment->proof_image) {
            $paymentData['proof_image_url'] = asset('storage/' . $payment->proof_image);
        }

        return response()->json([
            'message' => 'Payment retrieved successfully',
            'payment' => $paymentData,
        ]);
    }

    /**
     * Get subscription/payment statistics (Admin only).
     *
     * GET /api/admin/subscriptions/stats
     */
    public function stats()
    {
        $stats = [
            'subscriptions' => [
                'total' => Subscription::count(),
                'active' => Subscription::where('status', 'active')->where('end_date', '>=', now())->count(),
                'pending' => Subscription::where('status', 'pending')->count(),
                'expired' => Subscription::where('status', 'expired')->orWhere(function ($q) {
                    $q->where('status', 'active')->where('end_date', '<', now());
                })->count(),
                'cancelled' => Subscription::where('status', 'cancelled')->count(),
            ],
            'payments' => [
                'total' => Payment::count(),
                'pending' => Payment::where('status', 'pending')->count(),
                'approved' => Payment::where('status', 'approved')->count(),
                'rejected' => Payment::where('status', 'rejected')->count(),
                'refunded' => Payment::where('refund_status', 'refunded')->count(),
                'partially_refunded' => Payment::where('refund_status', 'partially_refunded')->count(),
            ],
            'revenue' => [
                'total' => (float) Payment::where('status', 'approved')->sum('amount'),
                'refunded' => (float) Payment::whereNotNull('refunded_amount')->sum('refunded_amount'),
                'net' => (float) Payment::where('status', 'approved')->sum('amount') - (float) Payment::whereNotNull('refunded_amount')->sum('refunded_amount'),
                'this_month' => (float) Payment::where('status', 'approved')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('amount'),
            ],
        ];

        return response()->json($stats);
    }
}