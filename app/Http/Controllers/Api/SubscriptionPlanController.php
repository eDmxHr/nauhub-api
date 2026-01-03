<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    /**
     * List all subscription plans (admin)
     *
     * GET /api/admin/plans
     */
    public function index(Request $request)
    {
        $query = SubscriptionPlan::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by tier
        if ($request->has('tier')) {
            $query->where('tier', $request->input('tier'));
        }

        // Filter by duration
        if ($request->has('duration')) {
            $query->where('duration', $request->input('duration'));
        }

        $plans = $query->orderBy('sort_order')->get();

        return response()->json([
            'plans' => $plans
        ]);
    }

    /**
     * Create new subscription plan
     *
     * POST /api/admin/plans
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|string|unique:subscription_plans,plan_id|max:50',
            'tier' => 'required|in:basic,standard,premium',
            'name' => 'required|string|max:100',
            'duration' => 'required|in:monthly,annual',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'duration_days' => 'required|integer|min:1',
            'features' => 'required|array|min:1',
            'features.*' => 'string|max:255',
            'is_popular' => 'nullable|boolean',
            'savings_text' => 'nullable|string|max:50',
            'discount_text' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $plan = SubscriptionPlan::create($validated);

        return response()->json([
            'message' => 'Subscription plan created successfully',
            'plan' => $plan
        ], 201);
    }

    /**
     * Get single subscription plan
     *
     * GET /api/admin/plans/{id}
     */
    public function show($id)
    {
        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return response()->json([
                'message' => 'Plan not found'
            ], 404);
        }

        return response()->json([
            'plan' => $plan
        ]);
    }

    /**
     * Update subscription plan
     *
     * PUT /api/admin/plans/{id}
     */
    public function update(Request $request, $id)
    {
        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return response()->json([
                'message' => 'Plan not found'
            ], 404);
        }

        $validated = $request->validate([
            'plan_id' => ['nullable', 'string', 'max:50', Rule::unique('subscription_plans')->ignore($plan->id)],
            'tier' => 'nullable|in:basic,standard,premium',
            'name' => 'nullable|string|max:100',
            'duration' => 'nullable|in:monthly,annual',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'duration_days' => 'nullable|integer|min:1',
            'features' => 'nullable|array|min:1',
            'features.*' => 'string|max:255',
            'is_popular' => 'nullable|boolean',
            'savings_text' => 'nullable|string|max:50',
            'discount_text' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $plan->update($validated);

        return response()->json([
            'message' => 'Subscription plan updated successfully',
            'plan' => $plan->fresh()
        ]);
    }

    /**
     * Delete subscription plan
     *
     * DELETE /api/admin/plans/{id}
     */
    public function destroy($id)
    {
        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return response()->json([
                'message' => 'Plan not found'
            ], 404);
        }

        // Check if plan has active subscriptions
        $activeSubscriptionsCount = Subscription::where('plan_type', $plan->duration)
            ->where('status', 'active')
            ->count();

        if ($activeSubscriptionsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete plan with active subscriptions',
                'active_subscriptions' => $activeSubscriptionsCount,
                'suggestion' => 'Consider deactivating the plan instead'
            ], 400);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Subscription plan deleted successfully'
        ]);
    }

    /**
     * Toggle plan active status
     *
     * PUT /api/admin/plans/{id}/toggle-active
     */
    public function toggleActive($id)
    {
        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return response()->json([
                'message' => 'Plan not found'
            ], 404);
        }

        $plan->is_active = !$plan->is_active;
        $plan->save();

        $message = $plan->is_active
            ? 'Plan activated successfully'
            : 'Plan deactivated successfully';

        return response()->json([
            'message' => $message,
            'plan' => $plan
        ]);
    }
}
