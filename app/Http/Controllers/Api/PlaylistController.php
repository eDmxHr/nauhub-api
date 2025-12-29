<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Profile;
use App\Models\Video;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    /**
     * Get all playlists for a profile (including "My List").
     */
    public function index(Request $request)
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

        $playlists = Playlist::withCount('items')
            ->where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->orderBy('is_system', 'desc') // System playlists first
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'message' => 'Playlists retrieved successfully',
            'playlists' => $playlists,
        ]);
    }

    /**
     * Get or create "My List" playlist for a profile.
     * This is the main Netflix-style "My List" feature.
     */
    public function getMyList(Request $request)
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

        // Get or create "My List" system playlist
        $myList = Playlist::firstOrCreate(
            [
                'user_id' => $userId,
                'profile_id' => $profileId,
                'is_system' => true,
            ],
            [
                'name' => 'My List',
                'description' => 'Your personal watchlist',
            ]
        );

        // Load videos in the playlist
        $myList->load(['videos' => function ($query) {
            $query->with(['categories', 'genres']);
        }]);

        return response()->json([
            'message' => 'My List retrieved successfully',
            'playlist' => $myList,
            'videos' => $myList->videos,
        ]);
    }

    /**
     * Add a video to "My List".
     */
    public function addToMyList(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'video_id' => 'required|exists:videos,id',
        ]);

        $userId = $request->user()->id;
        $profileId = $validated['profile_id'];
        $videoId = $validated['video_id'];

        // Verify profile belongs to user
        $profile = Profile::where('id', $profileId)->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found or does not belong to you',
            ], 403);
        }

        // Get or create "My List" system playlist
        $myList = Playlist::firstOrCreate(
            [
                'user_id' => $userId,
                'profile_id' => $profileId,
                'is_system' => true,
            ],
            [
                'name' => 'My List',
                'description' => 'Your personal watchlist',
            ]
        );

        // Check if video already exists in playlist
        $existingItem = PlaylistItem::where('playlist_id', $myList->id)
            ->where('video_id', $videoId)
            ->first();

        if ($existingItem) {
            return response()->json([
                'message' => 'Video already in My List',
            ], 400);
        }

        // Add video to playlist
        $playlistItem = PlaylistItem::create([
            'playlist_id' => $myList->id,
            'video_id' => $videoId,
            'sort_order' => PlaylistItem::where('playlist_id', $myList->id)->max('sort_order') + 1,
        ]);

        return response()->json([
            'message' => 'Video added to My List',
            'playlist_item' => $playlistItem->load('video'),
        ], 201);
    }

    /**
     * Remove a video from "My List".
     */
    public function removeFromMyList(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'video_id' => 'required|exists:videos,id',
        ]);

        $userId = $request->user()->id;
        $profileId = $validated['profile_id'];
        $videoId = $validated['video_id'];

        // Verify profile belongs to user
        $profile = Profile::where('id', $profileId)->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found or does not belong to you',
            ], 403);
        }

        // Find "My List" playlist
        $myList = Playlist::where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('is_system', true)
            ->first();

        if (!$myList) {
            return response()->json([
                'message' => 'My List not found',
            ], 404);
        }

        // Remove video from playlist
        $deleted = PlaylistItem::where('playlist_id', $myList->id)
            ->where('video_id', $videoId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Video not found in My List',
            ], 404);
        }

        return response()->json([
            'message' => 'Video removed from My List',
        ]);
    }

    /**
     * Check if a video is in "My List".
     */
    public function isInMyList(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'video_id' => 'required|exists:videos,id',
        ]);

        $userId = $request->user()->id;
        $profileId = $validated['profile_id'];
        $videoId = $validated['video_id'];

        // Verify profile belongs to user
        $profile = Profile::where('id', $profileId)->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found or does not belong to you',
            ], 403);
        }

        // Find "My List" playlist
        $myList = Playlist::where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('is_system', true)
            ->first();

        if (!$myList) {
            return response()->json([
                'message' => 'Not in My List',
                'in_list' => false,
            ]);
        }

        // Check if video exists in playlist
        $inList = PlaylistItem::where('playlist_id', $myList->id)
            ->where('video_id', $videoId)
            ->exists();

        return response()->json([
            'message' => $inList ? 'Video is in My List' : 'Not in My List',
            'in_list' => $inList,
        ]);
    }

    /**
     * Create a custom playlist (user-created, not system).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
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

        $playlist = Playlist::create([
            'user_id' => $userId,
            'profile_id' => $profileId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        return response()->json([
            'message' => 'Playlist created successfully',
            'playlist' => $playlist,
        ], 201);
    }

    /**
     * Get a specific playlist with its videos.
     */
    public function show(Request $request, $id)
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

        $playlist = Playlist::with(['videos' => function ($query) {
            $query->with(['categories', 'genres']);
        }])
            ->where('id', $id)
            ->where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->first();

        if (!$playlist) {
            return response()->json([
                'message' => 'Playlist not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Playlist retrieved successfully',
            'playlist' => $playlist,
            'videos' => $playlist->videos,
        ]);
    }

    /**
     * Update a custom playlist.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
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

        $playlist = Playlist::where('id', $id)
            ->where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('is_system', false) // Can't update system playlists
            ->first();

        if (!$playlist) {
            return response()->json([
                'message' => 'Playlist not found or cannot be updated',
            ], 404);
        }

        $playlist->update([
            'name' => $validated['name'] ?? $playlist->name,
            'description' => $validated['description'] ?? $playlist->description,
        ]);

        return response()->json([
            'message' => 'Playlist updated successfully',
            'playlist' => $playlist->fresh(),
        ]);
    }

    /**
     * Delete a custom playlist.
     */
    public function destroy(Request $request, $id)
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

        $playlist = Playlist::where('id', $id)
            ->where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('is_system', false) // Can't delete system playlists
            ->first();

        if (!$playlist) {
            return response()->json([
                'message' => 'Playlist not found or cannot be deleted',
            ], 404);
        }

        $playlist->delete();

        return response()->json([
            'message' => 'Playlist deleted successfully',
        ]);
    }

    /**
     * Add a video to a custom playlist.
     */
    public function addVideo(Request $request, $playlistId)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'video_id' => 'required|exists:videos,id',
        ]);

        $userId = $request->user()->id;
        $profileId = $validated['profile_id'];
        $videoId = $validated['video_id'];

        // Verify profile belongs to user
        $profile = Profile::where('id', $profileId)->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found or does not belong to you',
            ], 403);
        }

        $playlist = Playlist::where('id', $playlistId)
            ->where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->first();

        if (!$playlist) {
            return response()->json([
                'message' => 'Playlist not found',
            ], 404);
        }

        // Check if video already exists in playlist
        $existingItem = PlaylistItem::where('playlist_id', $playlistId)
            ->where('video_id', $videoId)
            ->first();

        if ($existingItem) {
            return response()->json([
                'message' => 'Video already in playlist',
            ], 400);
        }

        // Add video to playlist
        $playlistItem = PlaylistItem::create([
            'playlist_id' => $playlistId,
            'video_id' => $videoId,
            'sort_order' => PlaylistItem::where('playlist_id', $playlistId)->max('sort_order') + 1,
        ]);

        return response()->json([
            'message' => 'Video added to playlist',
            'playlist_item' => $playlistItem->load('video'),
        ], 201);
    }

    /**
     * Remove a video from a custom playlist.
     */
    public function removeVideo(Request $request, $playlistId)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'video_id' => 'required|exists:videos,id',
        ]);

        $userId = $request->user()->id;
        $profileId = $validated['profile_id'];
        $videoId = $validated['video_id'];

        // Verify profile belongs to user
        $profile = Profile::where('id', $profileId)->where('user_id', $userId)->first();
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found or does not belong to you',
            ], 403);
        }

        $playlist = Playlist::where('id', $playlistId)
            ->where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->first();

        if (!$playlist) {
            return response()->json([
                'message' => 'Playlist not found',
            ], 404);
        }

        // Remove video from playlist
        $deleted = PlaylistItem::where('playlist_id', $playlistId)
            ->where('video_id', $videoId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Video not found in playlist',
            ], 404);
        }

        return response()->json([
            'message' => 'Video removed from playlist',
        ]);
    }
}