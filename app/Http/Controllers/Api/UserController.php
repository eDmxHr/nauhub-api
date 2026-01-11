<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List all users with pagination, filters, and search
     *
     * GET /api/admin/users
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $role = $request->input('role');
        $isSubscribed = $request->input('is_subscribed');
        $isActive = $request->input('is_active');
        $phoneVerified = $request->input('phone_verified');
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'newest');

        $query = User::query();

        // Apply filters
        if ($role) {
            $query->where('role', $role);
        }

        if ($isSubscribed !== null) {
            $query->where('is_subscribed', $isSubscribed);
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($phoneVerified !== null) {
            $query->where('phone_verified', $phoneVerified);
        }

        // Search by name, email, or phone
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        switch ($sortBy) {
            case 'oldest':
                $query->oldest();
                break;
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'newest':
            default:
                $query->latest();
                break;
        }

        $users = $query->with([
            'profiles:id,user_id,name,avatar',
            'subscriptions' => function ($q) {
                $q->where('status', 'active')
                  ->where('end_date', '>=', now())
                  ->orderBy('end_date', 'desc')
                  ->limit(1);
            }
        ])->paginate($perPage);

        // Add current subscription plan info to each user
        $users->getCollection()->transform(function ($user) {
            $userData = $user->toArray();

            // Get current subscription
            $currentSubscription = $user->subscriptions->first();

            if ($currentSubscription) {
                $subscriptionPlan = \App\Models\SubscriptionPlan::where('duration', $currentSubscription->plan_type)
                    ->where('is_active', true)
                    ->first();

                $userData['current_subscription'] = [
                    'id' => $currentSubscription->id,
                    'plan_type' => $currentSubscription->plan_type,
                    'end_date' => $currentSubscription->end_date->format('Y-m-d'),
                    'days_remaining' => now()->diffInDays($currentSubscription->end_date),
                    'plan_name' => $subscriptionPlan ? $subscriptionPlan->name : ucfirst($currentSubscription->plan_type),
                ];
            } else {
                $userData['current_subscription'] = null;
            }

            // Remove the subscriptions array to keep response clean
            unset($userData['subscriptions']);

            return $userData;
        });

        return response()->json($users);
    }

    /**
     * Get user statistics
     *
     * GET /api/admin/users/stats
     */
    public function stats()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'blocked_users' => User::where('is_active', false)->count(),
            'total_admins' => User::where('role', 'admin')->count(),
            'subscribed_users' => User::where('is_subscribed', true)->count(),
            'phone_verified_users' => User::where('phone_verified', true)->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get single user with full details
     *
     * GET /api/admin/users/{id}
     */
    public function show($id)
    {
        $user = User::with([
            'subscriptions' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            },
            'payments' => function ($query) {
                $query->with('subscription')->orderBy('created_at', 'desc')->limit(10);
            },
            'profiles',
            'watchHistory' => function ($query) {
                $query->with(['video', 'episode'])->latest()->limit(5);
            },
            'playlists' => function ($query) {
                $query->withCount('videos')->latest()->limit(5);
            },
        ])->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Get current active subscription with plan details
        $currentSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->orderBy('end_date', 'desc')
            ->first();

        // If user has active subscription, get the plan details
        $subscriptionPlan = null;
        if ($currentSubscription) {
            $subscriptionPlan = \App\Models\SubscriptionPlan::where('duration', $currentSubscription->plan_type)
                ->where('is_active', true)
                ->first();
        }

        // Build response data
        $userData = $user->toArray();

        // Add current subscription with plan details
        $userData['current_subscription'] = $currentSubscription ? [
            'id' => $currentSubscription->id,
            'plan_type' => $currentSubscription->plan_type,
            'amount' => (float) $currentSubscription->amount,
            'currency' => $currentSubscription->currency,
            'start_date' => $currentSubscription->start_date->format('Y-m-d'),
            'end_date' => $currentSubscription->end_date->format('Y-m-d'),
            'status' => $currentSubscription->status,
            'days_remaining' => now()->diffInDays($currentSubscription->end_date),
            'auto_renew' => $currentSubscription->auto_renew,
            'plan_details' => $subscriptionPlan ? [
                'plan_id' => $subscriptionPlan->plan_id,
                'tier' => $subscriptionPlan->tier,
                'name' => $subscriptionPlan->name,
                'duration' => $subscriptionPlan->duration,
                'price' => (float) $subscriptionPlan->price,
                'features' => $subscriptionPlan->features,
            ] : null,
        ] : null;

        // Add comprehensive stats
        $userData['stats'] = [
            'total_subscriptions' => $user->subscriptions()->count(),
            'active_subscriptions' => $user->subscriptions()->where('status', 'active')->where('end_date', '>=', now())->count(),
            'expired_subscriptions' => $user->subscriptions()->where('status', 'expired')->count(),
            'cancelled_subscriptions' => $user->subscriptions()->where('status', 'cancelled')->count(),
            'total_payments' => $user->payments()->count(),
            'approved_payments' => $user->payments()->where('status', 'approved')->count(),
            'pending_payments' => $user->payments()->where('status', 'pending')->count(),
            'rejected_payments' => $user->payments()->where('status', 'rejected')->count(),
            'total_spent' => (float) $user->payments()->where('status', 'approved')->sum('amount'),
            'profile_count' => $user->profiles()->count(),
            'watch_history_count' => $user->watchHistory()->count(),
            'playlist_count' => $user->playlists()->count(),
        ];

        // Add account info
        $userData['account_info'] = [
            'member_since' => $user->created_at->format('Y-m-d H:i:s'),
            'member_for_days' => now()->diffInDays($user->created_at),
            'last_updated' => $user->updated_at->format('Y-m-d H:i:s'),
        ];

        return response()->json($userData);
    }

    /**
     * Update user role (user <-> admin)
     *
     * PUT /api/admin/users/{id}/role
     */
    public function updateRole(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'role' => ['required', Rule::in(['user', 'admin'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Prevent admin from demoting themselves
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot change your own role'
            ], 403);
        }

        $oldRole = $user->role;
        $user->role = $request->input('role');
        $user->save();

        return response()->json([
            'message' => "User role updated from {$oldRole} to {$user->role}",
            'user' => $user
        ]);
    }

    /**
     * Toggle user active status (block/unblock)
     *
     * PUT /api/admin/users/{id}/toggle-active
     */
    public function toggleActive(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Prevent admin from blocking themselves
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot block your own account'
            ], 403);
        }

        // Toggle the active status
        $user->is_active = !$user->is_active;
        $user->save();

        $message = $user->is_active
            ? 'User account activated successfully'
            : 'User account blocked successfully';

        return response()->json([
            'message' => $message,
            'is_active' => $user->is_active,
            'user' => $user
        ]);
    }
}
