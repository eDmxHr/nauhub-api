<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WatchHistory;
use App\Models\Video;
use App\Models\Episode;
use App\Models\Profile;
use Illuminate\Http\Request;

class WatchHistoryController extends Controller
{
    /**
     * Get watch history for the authenticated user's profile.
     * Returns all watched videos/episodes with resume points.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'type' => 'nullable|in:movie,series,anime,all', // Filter by content type
            'completed' => 'nullable|boolean', // Filter completed vs in-progress
        ]);

        $userId = $request->user()->id;
        $profileId = $validated['profile_id'];

        // Verify profile belongs to user
        $profile = Profile::where('id', $profileId)->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found or does not belong to you',
            ], 403);
        }

        $query = WatchHistory::with(['video', 'episode.season'])
            ->where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->orderBy('last_watched_at', 'desc');

        // Filter by completion status
        if (isset($validated['completed'])) {
            $query->where('completed', $validated['completed']);
        }

        // Filter by content type
        if (isset($validated['type']) && $validated['type'] !== 'all') {
            $query->whereHas('video', function ($q) use ($validated) {
                $q->where('type', $validated['type']);
            });
        }

        $history = $query->paginate(20);

        return response()->json([
            'message' => 'Watch history retrieved successfully',
            'history' => $history,
        ]);
    }

    /**
     * Get "Continue Watching" - incomplete videos/episodes.
     * Returns content user started but hasn't finished (progress < 90%).
     */
    public function continueWatching(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
        ]);

        $userId = $request->user()->id;
        $profileId = $validated['profile_id'];

        // Verify profile belongs to user
        $profile = Profile::where('id', $profileId)->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found or does not belong to you',
            ], 403);
        }

        $continueWatching = WatchHistory::with(['video', 'episode.season'])
            ->where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('completed', false)
            ->where('progress_seconds', '>', 0) // Must have started watching
            ->orderBy('last_watched_at', 'desc')
            ->limit(12)
            ->get();

        return response()->json([
            'message' => 'Continue watching retrieved successfully',
            'items' => $continueWatching,
        ]);
    }

    /**
     * Update playback progress for a movie or episode.
     * Auto-marks as completed if progress > 90%.
     */
    public function updateProgress(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'video_id' => 'required|exists:videos,id',
            'episode_id' => 'nullable|exists:episodes,id',
            'progress_seconds' => 'required|integer|min:0',
            'duration_seconds' => 'nullable|integer|min:1',
        ]);

        $userId = $request->user()->id;
        $profileId = $validated['profile_id'];

        // Verify profile belongs to user
        $profile = Profile::where('id', $profileId)->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found or does not belong to you',
            ], 403);
        }

        // Validate episode belongs to video if provided
        if (!empty($validated['episode_id'])) {
            $episode = Episode::with('season')->find($validated['episode_id']);
            if (!$episode || $episode->season->video_id != $validated['video_id']) {
                return response()->json([
                    'message' => 'Episode does not belong to the specified video',
                ], 400);
            }
        }

        // Calculate completion status (>90% = completed)
        $completed = false;
        if (!empty($validated['duration_seconds'])) {
            $progressPercentage = ($validated['progress_seconds'] / $validated['duration_seconds']) * 100;
            $completed = $progressPercentage >= 90;
        }

        // Find or create watch history record
        $watchHistory = WatchHistory::updateOrCreate(
            [
                'user_id' => $userId,
                'profile_id' => $profileId,
                'video_id' => $validated['video_id'],
                'episode_id' => $validated['episode_id'] ?? null,
            ],
            [
                'progress_seconds' => $validated['progress_seconds'],
                'duration_seconds' => $validated['duration_seconds'] ?? null,
                'completed' => $completed,
                'last_watched_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Progress updated successfully',
            'watch_history' => $watchHistory->fresh(),
            'progress_percentage' => $watchHistory->progress_percentage,
        ]);
    }

    /**
     * Get resume point for a specific video or episode.
     * Returns where the user left off.
     */
    public function getResumePoint(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'video_id' => 'required|exists:videos,id',
            'episode_id' => 'nullable|exists:episodes,id',
        ]);

        $userId = $request->user()->id;
        $profileId = $validated['profile_id'];

        // Verify profile belongs to user
        $profile = Profile::where('id', $profileId)->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found or does not belong to you',
            ], 403);
        }

        $watchHistory = WatchHistory::where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('video_id', $validated['video_id'])
            ->where('episode_id', $validated['episode_id'] ?? null)
            ->first();

        if (!$watchHistory) {
            return response()->json([
                'message' => 'No watch history found',
                'resume_point' => 0,
                'completed' => false,
            ]);
        }

        return response()->json([
            'message' => 'Resume point retrieved successfully',
            'resume_point' => $watchHistory->progress_seconds,
            'duration' => $watchHistory->duration_seconds,
            'progress_percentage' => $watchHistory->progress_percentage,
            'completed' => $watchHistory->completed,
            'last_watched_at' => $watchHistory->last_watched_at,
        ]);
    }

    /**
     * Mark a video/episode as completed manually.
     */
    public function markAsCompleted(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'video_id' => 'required|exists:videos,id',
            'episode_id' => 'nullable|exists:episodes,id',
        ]);

        $userId = $request->user()->id;
        $profileId = $validated['profile_id'];

        // Verify profile belongs to user
        $profile = Profile::where('id', $profileId)->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found or does not belong to you',
            ], 403);
        }

        $watchHistory = WatchHistory::updateOrCreate(
            [
                'user_id' => $userId,
                'profile_id' => $profileId,
                'video_id' => $validated['video_id'],
                'episode_id' => $validated['episode_id'] ?? null,
            ],
            [
                'completed' => true,
                'last_watched_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Marked as completed',
            'watch_history' => $watchHistory,
        ]);
    }

    /**
     * Remove an item from watch history.
     */
    public function remove(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'video_id' => 'required|exists:videos,id',
            'episode_id' => 'nullable|exists:episodes,id',
        ]);

        $userId = $request->user()->id;
        $profileId = $validated['profile_id'];

        // Verify profile belongs to user
        $profile = Profile::where('id', $profileId)->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found or does not belong to you',
            ], 403);
        }

        $deleted = WatchHistory::where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('video_id', $validated['video_id'])
            ->where('episode_id', $validated['episode_id'] ?? null)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Watch history not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Removed from watch history',
        ]);
    }

    /**
     * Clear all watch history for a profile.
     */
    public function clearAll(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
        ]);

        $userId = $request->user()->id;
        $profileId = $validated['profile_id'];

        // Verify profile belongs to user
        $profile = Profile::where('id', $profileId)->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found or does not belong to you',
            ], 403);
        }

        $deletedCount = WatchHistory::where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->delete();

        return response()->json([
            'message' => 'Watch history cleared',
            'deleted_count' => $deletedCount,
        ]);
    }
}
