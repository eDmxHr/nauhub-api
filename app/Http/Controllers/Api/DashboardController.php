<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Video;
use App\Models\Season;
use App\Models\Episode;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get admin dashboard statistics
     */
    public function index()
    {
        $stats = [
            'users' => $this->getUserStats(),
            'revenue' => $this->getRevenueStats(),
            'content' => $this->getContentStats(),
            'subscriptions' => $this->getSubscriptionStats(),
            'recent_activity' => $this->getRecentActivity(),
        ];

        return response()->json([
            'message' => 'Dashboard statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * Get user statistics
     */
    protected function getUserStats()
    {
        $now = Carbon::now();

        return [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
            'subscribed' => User::where('is_subscribed', true)->count(),
            'admins' => User::where('role', 'admin')->count(),
            'new_today' => User::whereDate('created_at', $now->toDateString())->count(),
            'new_this_week' => User::whereBetween('created_at', [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek()
            ])->count(),
            'new_this_month' => User::whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month)
                ->count(),
        ];
    }

    /**
     * Get revenue statistics
     */
    protected function getRevenueStats()
    {
        $now = Carbon::now();

        return [
            'total_revenue' => Payment::where('status', 'approved')->sum('amount'),
            'revenue_this_month' => Payment::where('status', 'approved')
                ->whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month)
                ->sum('amount'),
            'revenue_this_year' => Payment::where('status', 'approved')
                ->whereYear('created_at', $now->year)
                ->sum('amount'),
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'pending_amount' => Payment::where('status', 'pending')->sum('amount'),
            'approved_payments' => Payment::where('status', 'approved')->count(),
            'rejected_payments' => Payment::where('status', 'rejected')->count(),
            'awaiting_verification' => Payment::where('status', 'pending')
                ->whereNotNull('proof_image')
                ->count(),
        ];
    }

    /**
     * Get content statistics
     */
    protected function getContentStats()
    {
        return [
            'total_videos' => Video::count(),
            'movies' => Video::where('type', 'movie')->count(),
            'series' => Video::where('type', 'series')->count(),
            'anime' => Video::where('type', 'anime')->count(),
            'total_seasons' => Season::count(),
            'total_episodes' => Episode::count(),
            'categories' => Category::count(),
            'genres' => Genre::count(),
            'videos_by_status' => [
                'processing' => Video::where('status', 'processing')->count(),
                'ready' => Video::where('status', 'ready')->count(),
                'failed' => Video::where('status', 'failed')->count(),
            ],
        ];
    }

    /**
     * Get subscription statistics
     */
    protected function getSubscriptionStats()
    {
        $now = Carbon::now();

        return [
            'total' => Subscription::count(),
            'active' => Subscription::where('status', 'active')->count(),
            'expired' => Subscription::where('status', 'expired')->count(),
            'cancelled' => Subscription::where('status', 'cancelled')->count(),
            'expiring_soon' => Subscription::where('status', 'active')
                ->whereBetween('end_date', [$now, $now->copy()->addDays(7)])
                ->count(),
            'new_this_month' => Subscription::whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month)
                ->count(),
            'auto_renew_enabled' => Subscription::where('auto_renew', true)
                ->where('status', 'active')
                ->count(),
        ];
    }

    /**
     * Get recent activity for dashboard
     */
    protected function getRecentActivity()
    {
        return [
            'recent_users' => User::latest()
                ->take(5)
                ->get(['id', 'name', 'email', 'role', 'created_at'])
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'created_at' => $user->created_at->toISOString(),
                    ];
                }),

            'recent_subscriptions' => Subscription::with('user:id,name,email')
                ->latest()
                ->take(5)
                ->get(['id', 'user_id', 'plan_type', 'amount', 'status', 'created_at'])
                ->map(function ($subscription) {
                    return [
                        'id' => $subscription->id,
                        'user' => [
                            'id' => $subscription->user->id,
                            'name' => $subscription->user->name,
                            'email' => $subscription->user->email,
                        ],
                        'plan_type' => $subscription->plan_type,
                        'amount' => (float) $subscription->amount,
                        'status' => $subscription->status,
                        'created_at' => $subscription->created_at->toISOString(),
                    ];
                }),

            'pending_payments' => Payment::with('user:id,name,email')
                ->where('status', 'pending')
                ->latest()
                ->take(5)
                ->get(['id', 'user_id', 'subscription_id', 'amount', 'reference_code', 'created_at'])
                ->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'user' => [
                            'id' => $payment->user->id,
                            'name' => $payment->user->name,
                            'email' => $payment->user->email,
                        ],
                        'amount' => (float) $payment->amount,
                        'reference_code' => $payment->reference_code,
                        'created_at' => $payment->created_at->toISOString(),
                    ];
                }),

            'recent_videos' => Video::latest()
                ->take(5)
                ->get(['id', 'title', 'type', 'status', 'created_at'])
                ->map(function ($video) {
                    return [
                        'id' => $video->id,
                        'title' => $video->title,
                        'type' => $video->type,
                        'status' => $video->status,
                        'created_at' => $video->created_at->toISOString(),
                    ];
                }),
        ];
    }

    /**
     * Get chart data for revenue over time
     */
    public function revenueChart(Request $request)
    {
        $period = $request->input('period', 'month'); // 'week', 'month', 'year'
        $now = Carbon::now();

        $data = [];

        switch ($period) {
            case 'week':
                // Last 7 days
                for ($i = 6; $i >= 0; $i--) {
                    $date = $now->copy()->subDays($i);
                    $revenue = Payment::where('status', 'approved')
                        ->whereDate('created_at', $date->toDateString())
                        ->sum('amount');

                    $data[] = [
                        'date' => $date->format('Y-m-d'),
                        'label' => $date->format('D'),
                        'revenue' => (float) $revenue,
                    ];
                }
                break;

            case 'month':
                // Last 30 days
                for ($i = 29; $i >= 0; $i--) {
                    $date = $now->copy()->subDays($i);
                    $revenue = Payment::where('status', 'approved')
                        ->whereDate('created_at', $date->toDateString())
                        ->sum('amount');

                    $data[] = [
                        'date' => $date->format('Y-m-d'),
                        'label' => $date->format('M d'),
                        'revenue' => (float) $revenue,
                    ];
                }
                break;

            case 'year':
                // Last 12 months
                for ($i = 11; $i >= 0; $i--) {
                    $date = $now->copy()->subMonths($i);
                    $revenue = Payment::where('status', 'approved')
                        ->whereYear('created_at', $date->year)
                        ->whereMonth('created_at', $date->month)
                        ->sum('amount');

                    $data[] = [
                        'date' => $date->format('Y-m'),
                        'label' => $date->format('M Y'),
                        'revenue' => (float) $revenue,
                    ];
                }
                break;
        }

        return response()->json([
            'message' => 'Revenue chart data retrieved successfully',
            'period' => $period,
            'data' => $data
        ]);
    }

    /**
     * Get chart data for user growth over time
     */
    public function userGrowthChart(Request $request)
    {
        $period = $request->input('period', 'month'); // 'week', 'month', 'year'
        $now = Carbon::now();

        $data = [];

        switch ($period) {
            case 'week':
                // Last 7 days
                for ($i = 6; $i >= 0; $i--) {
                    $date = $now->copy()->subDays($i);
                    $newUsers = User::whereDate('created_at', $date->toDateString())->count();
                    $totalUsers = User::where('created_at', '<=', $date->endOfDay())->count();

                    $data[] = [
                        'date' => $date->format('Y-m-d'),
                        'label' => $date->format('D'),
                        'new_users' => $newUsers,
                        'total_users' => $totalUsers,
                    ];
                }
                break;

            case 'month':
                // Last 30 days
                for ($i = 29; $i >= 0; $i--) {
                    $date = $now->copy()->subDays($i);
                    $newUsers = User::whereDate('created_at', $date->toDateString())->count();
                    $totalUsers = User::where('created_at', '<=', $date->endOfDay())->count();

                    $data[] = [
                        'date' => $date->format('Y-m-d'),
                        'label' => $date->format('M d'),
                        'new_users' => $newUsers,
                        'total_users' => $totalUsers,
                    ];
                }
                break;

            case 'year':
                // Last 12 months
                for ($i = 11; $i >= 0; $i--) {
                    $date = $now->copy()->subMonths($i);
                    $newUsers = User::whereYear('created_at', $date->year)
                        ->whereMonth('created_at', $date->month)
                        ->count();
                    $totalUsers = User::where('created_at', '<=', $date->endOfMonth())->count();

                    $data[] = [
                        'date' => $date->format('Y-m'),
                        'label' => $date->format('M Y'),
                        'new_users' => $newUsers,
                        'total_users' => $totalUsers,
                    ];
                }
                break;
        }

        return response()->json([
            'message' => 'User growth chart data retrieved successfully',
            'period' => $period,
            'data' => $data
        ]);
    }
}
