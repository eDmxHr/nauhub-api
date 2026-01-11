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
}