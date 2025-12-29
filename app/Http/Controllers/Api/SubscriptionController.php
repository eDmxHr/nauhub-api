<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Payment;
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
        $plans = [
            // Basic Plan
            [
                'id' => 'basic_monthly',
                'tier' => 'basic',
                'name' => 'Basic - Monthly',
                'duration' => 'monthly',
                'price' => 99.00,
                'currency' => 'MVR',
                'duration_days' => 30,
                'features' => [
                    '1 screen at a time',
                    '1 profile',
                    'Standard content library access',
                ],
            ],
            [
                'id' => 'basic_annual',
                'tier' => 'basic',
                'name' => 'Basic - Annual',
                'duration' => 'annual',
                'price' => 990.00,
                'currency' => 'MVR',
                'duration_days' => 365,
                'savings' => 'MVR 198',
                'discount_text' => '2 months free!',
                'features' => [
                    '1 screen at a time',
                    '1 profile',
                    'Standard content library access',
                ],
            ],

            // Standard Plan (Most Popular)
            [
                'id' => 'standard_monthly',
                'tier' => 'standard',
                'name' => 'Standard - Monthly',
                'duration' => 'monthly',
                'price' => 149.00,
                'currency' => 'MVR',
                'duration_days' => 30,
                'popular' => true,
                'features' => [
                    '2 screens simultaneously',
                    'Up to 3 profiles',
                    'Full content library',
                ],
            ],
            [
                'id' => 'standard_annual',
                'tier' => 'standard',
                'name' => 'Standard - Annual',
                'duration' => 'annual',
                'price' => 1490.00,
                'currency' => 'MVR',
                'duration_days' => 365,
                'savings' => 'MVR 298',
                'discount_text' => '2 months free!',
                'popular' => true,
                'features' => [
                    '2 screens simultaneously',
                    'Up to 3 profiles',
                    'Full content library',
                ],
            ],

            // Premium Plan
            [
                'id' => 'premium_monthly',
                'tier' => 'premium',
                'name' => 'Premium - Monthly',
                'duration' => 'monthly',
                'price' => 199.00,
                'currency' => 'MVR',
                'duration_days' => 30,
                'features' => [
                    '4 screens simultaneously',
                    'Up to 5 profiles',
                    'Full content library',
                ],
            ],
            [
                'id' => 'premium_annual',
                'tier' => 'premium',
                'name' => 'Premium - Annual',
                'duration' => 'annual',
                'price' => 1990.00,
                'currency' => 'MVR',
                'duration_days' => 365,
                'savings' => 'MVR 398',
                'discount_text' => '2 months free!',
                'features' => [
                    '4 screens simultaneously',
                    'Up to 5 profiles',
                    'Full content library',
                ],
            ],
        ];

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
            'plan_id' => 'required|in:basic_monthly,basic_annual,standard_monthly,standard_annual,premium_monthly,premium_annual',
            'proof_image' => 'required|image|max:5120', // 5MB max
        ]);

        $userId = $request->user()->id;

        // Define pricing
        $pricing = [
            'basic_monthly' => ['tier' => 'basic', 'duration' => 'monthly', 'amount' => 99.00, 'days' => 30],
            'basic_annual' => ['tier' => 'basic', 'duration' => 'annual', 'amount' => 990.00, 'days' => 365],
            'standard_monthly' => ['tier' => 'standard', 'duration' => 'monthly', 'amount' => 149.00, 'days' => 30],
            'standard_annual' => ['tier' => 'standard', 'duration' => 'annual', 'amount' => 1490.00, 'days' => 365],
            'premium_monthly' => ['tier' => 'premium', 'duration' => 'monthly', 'amount' => 199.00, 'days' => 30],
            'premium_annual' => ['tier' => 'premium', 'duration' => 'annual', 'amount' => 1990.00, 'days' => 365],
        ];

        $plan = $pricing[$validated['plan_id']];

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
        }

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
        }

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