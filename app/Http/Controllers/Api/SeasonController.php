<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\Video;
use Illuminate\Http\Request;

class SeasonController extends Controller
{
    /**
     * Get all seasons for a video.
     */
    public function index($videoId)
    {
        $video = Video::find($videoId);

        if (!$video) {
            return response()->json([
                'message' => 'Video not found',
            ], 404);
        }

        // Only allow seasons for series/anime
        if ($video->type === 'movie') {
            return response()->json([
                'message' => 'Movies do not have seasons',
            ], 400);
        }

        $seasons = $video->seasons()
            ->withCount('episodes')
            ->orderBy('season_number')
            ->get();

        return response()->json([
            'message' => 'Seasons retrieved successfully',
            'seasons' => $seasons,
        ]);
    }

    /**
     * Create a new season for a video (Admin only).
     */
    public function store(Request $request, $videoId)
    {
        $video = Video::find($videoId);

        if (!$video) {
            return response()->json([
                'message' => 'Video not found',
            ], 404);
        }

        // Only allow seasons for series/anime
        if ($video->type === 'movie') {
            return response()->json([
                'message' => 'Cannot add seasons to movies',
            ], 400);
        }

        $validated = $request->validate([
            'season_number' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|string|max:500',
            'release_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 5),
            'is_published' => 'nullable|boolean',
        ]);

        // Check if season number already exists for this video
        $existingSeason = Season::where('video_id', $videoId)
            ->where('season_number', $validated['season_number'])
            ->first();

        if ($existingSeason) {
            return response()->json([
                'message' => 'Season number already exists for this video',
            ], 400);
        }

        $validated['video_id'] = $videoId;
        $validated['is_published'] = $validated['is_published'] ?? false;

        $season = Season::create($validated);

        return response()->json([
            'message' => 'Season created successfully',
            'season' => $season,
        ], 201);
    }

    /**
     * Get a specific season with episodes.
     */
    public function show($id)
    {
        $season = Season::with(['video', 'episodes' => function ($query) {
            $query->orderBy('episode_number');
        }])->find($id);

        if (!$season) {
            return response()->json([
                'message' => 'Season not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Season retrieved successfully',
            'season' => $season,
        ]);
    }

    /**
     * Update a season (Admin only).
     */
    public function update(Request $request, $id)
    {
        $season = Season::find($id);

        if (!$season) {
            return response()->json([
                'message' => 'Season not found',
            ], 404);
        }

        $validated = $request->validate([
            'season_number' => 'sometimes|integer|min:1',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|string|max:500',
            'release_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 5),
            'is_published' => 'nullable|boolean',
        ]);

        // Check if new season number conflicts with existing
        if (isset($validated['season_number'])) {
            $existingSeason = Season::where('video_id', $season->video_id)
                ->where('season_number', $validated['season_number'])
                ->where('id', '!=', $id)
                ->first();

            if ($existingSeason) {
                return response()->json([
                    'message' => 'Season number already exists for this video',
                ], 400);
            }
        }

        $season->update($validated);

        return response()->json([
            'message' => 'Season updated successfully',
            'season' => $season->fresh(),
        ]);
    }

    /**
     * Delete a season (Admin only).
     */
    public function destroy($id)
    {
        $season = Season::withCount('episodes')->find($id);

        if (!$season) {
            return response()->json([
                'message' => 'Season not found',
            ], 404);
        }

        // Delete all episodes first
        $season->episodes()->delete();

        $season->delete();

        return response()->json([
            'message' => 'Season and all episodes deleted successfully',
        ]);
    }
}
