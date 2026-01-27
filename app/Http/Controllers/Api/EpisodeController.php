<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Season;
use Illuminate\Http\Request;

class EpisodeController extends Controller
{
    /**
     * Get all episodes for a season.
     */
    public function index($seasonId)
    {
        $season = Season::find($seasonId);

        if (!$season) {
            return response()->json([
                'message' => 'Season not found',
            ], 404);
        }

        $episodes = $season->episodes()
            ->orderBy('episode_number')
            ->get();

        return response()->json([
            'message' => 'Episodes retrieved successfully',
            'episodes' => $episodes,
        ]);
    }

    /**
     * Create a new episode for a season (Admin only).
     */
    public function store(Request $request, $seasonId)
    {
        $season = Season::find($seasonId);

        if (!$season) {
            return response()->json([
                'message' => 'Season not found',
            ], 404);
        }

        $validated = $request->validate([
            'episode_number' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|string|max:500',
            'cloudflare_video_uid' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,processing,ready,error',
            'magnet_url' => 'nullable|string',
            'duration' => 'nullable|integer|min:0',
            'quality' => 'nullable|string|max:50',
            'release_date' => 'nullable|date',
            'is_published' => 'nullable|boolean',
        ]);

        // Check if episode number already exists for this season
        $existingEpisode = Episode::where('season_id', $seasonId)
            ->where('episode_number', $validated['episode_number'])
            ->first();

        if ($existingEpisode) {
            return response()->json([
                'message' => 'Episode number already exists for this season',
            ], 400);
        }

        $magnetUrl = $validated['magnet_url'] ?? null;
        unset($validated['magnet_url']);

        $validated['season_id'] = $seasonId;
        $validated['is_published'] = $validated['is_published'] ?? false;
        $validated['view_count'] = 0;

        // If magnet URL is provided, initiate upload in background
        if ($magnetUrl) {
            $torrentService = app(\App\Services\TorrentToStreamService::class);

            $result = $torrentService->uploadFromMagnet($magnetUrl, [
                'name' => $validated['title'],
            ]);

            if ($result['success']) {
                // Store transfer ID temporarily (will be updated by background job with actual UID)
                $validated['cloudflare_video_uid'] = 'processing_transfer_' . $result['transfer_id'];
            } else {
                return response()->json([
                    'message' => 'Failed to initiate episode upload',
                    'error' => $result['message'] ?? 'Unknown error',
                ], 500);
            }
        }

        // Note: cloudflare_video_uid can be added later via update or file upload endpoints

        $episode = Episode::create($validated);

        return response()->json([
            'message' => 'Episode created successfully' . ($magnetUrl ? '. Upload processing in background.' : ''),
            'episode' => $episode,
        ], 201);
    }

    /**
     * Get a specific episode.
     */
    public function show($id)
    {
        $episode = Episode::with('season.video')->find($id);

        if (!$episode) {
            return response()->json([
                'message' => 'Episode not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Episode retrieved successfully',
            'episode' => $episode,
        ]);
    }

    /**
     * Update an episode (Admin only).
     */
    public function update(Request $request, $id)
    {
        $episode = Episode::find($id);

        if (!$episode) {
            return response()->json([
                'message' => 'Episode not found',
            ], 404);
        }

        $validated = $request->validate([
            'episode_number' => 'sometimes|integer|min:1',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|string|max:500',
            'cloudflare_video_uid' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,processing,ready,error',
            'duration' => 'nullable|integer|min:0',
            'quality' => 'nullable|string|max:50',
            'release_date' => 'nullable|date',
            'is_published' => 'nullable|boolean',
        ]);

        // Check if new episode number conflicts with existing
        if (isset($validated['episode_number'])) {
            $existingEpisode = Episode::where('season_id', $episode->season_id)
                ->where('episode_number', $validated['episode_number'])
                ->where('id', '!=', $id)
                ->first();

            if ($existingEpisode) {
                return response()->json([
                    'message' => 'Episode number already exists for this season',
                ], 400);
            }
        }

        $episode->update($validated);

        return response()->json([
            'message' => 'Episode updated successfully',
            'episode' => $episode->fresh(),
        ]);
    }

    /**
     * Delete an episode (Admin only).
     */
    public function destroy($id)
    {
        $episode = Episode::find($id);

        if (!$episode) {
            return response()->json([
                'message' => 'Episode not found',
            ], 404);
        }

        // Placeholder: In production, delete from Cloudflare Stream
        // CloudflareService::deleteVideo($episode->cloudflare_video_uid);

        $episode->delete();

        return response()->json([
            'message' => 'Episode deleted successfully',
        ]);
    }

    /**
     * Increment view count for an episode.
     */
    public function incrementView($id)
    {
        $episode = Episode::find($id);

        if (!$episode) {
            return response()->json([
                'message' => 'Episode not found',
            ], 404);
        }

        $episode->increment('view_count');

        return response()->json([
            'message' => 'View count updated',
            'view_count' => $episode->view_count,
        ]);
    }
}
