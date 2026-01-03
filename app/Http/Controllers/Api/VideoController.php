<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    /**
     * Admin: Display all videos including unpublished ones
     */
    public function adminIndex(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $type = $request->input('type'); // movie, series, anime
        $status = $request->input('status'); // pending, processing, ready, error
        $published = $request->input('published'); // Don't filter by default
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'newest');

        $query = Video::query()
            ->with(['categories', 'genres', 'seasons']);

        // Search by title or description
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        // Filter by type
        if ($type) {
            $query->where('type', $type);
        }

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        // Filter by published status (optional for admin)
        if ($published !== null) {
            $query->where('is_published', $published);
        }

        // Sorting
        switch ($sortBy) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'rating':
                $query->orderBy('imdb_rating', 'desc');
                break;
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            case 'views':
                $query->orderBy('view_count', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $videos = $query->paginate($perPage);

        return response()->json($videos);
    }

    /**
     * Display a paginated listing of videos with search and filters.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $type = $request->input('type'); // movie, series, anime
        $categoryId = $request->input('category_id');
        $genreId = $request->input('genre_id');
        $featured = $request->input('featured');
        $trending = $request->input('trending');
        $published = $request->input('published', true);
        $releaseYear = $request->input('release_year');
        $minRating = $request->input('min_rating'); // Minimum IMDb rating
        $sortBy = $request->input('sort_by', 'newest'); // newest, oldest, rating, title, views
        $search = $request->input('search'); // Search query

        $query = Video::query()
            ->with(['categories', 'genres', 'seasons']);

        // Search by title or description
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        // Filter by type
        if ($type) {
            $query->where('type', $type);
        }

        // Filter by published status
        if ($published !== null) {
            $query->where('is_published', $published);
        }

        // Filter by featured
        if ($featured !== null) {
            $query->where('is_featured', $featured);
        }

        // Filter by trending
        if ($trending !== null) {
            $query->where('is_trending', $trending);
        }

        // Filter by release year
        if ($releaseYear) {
            $query->where('release_year', $releaseYear);
        }

        // Filter by minimum rating
        if ($minRating) {
            $query->where('imdb_rating', '>=', $minRating);
        }

        // Filter by category
        if ($categoryId) {
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        // Filter by genre
        if ($genreId) {
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        // Sorting
        switch ($sortBy) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'rating':
                $query->orderBy('imdb_rating', 'desc');
                break;
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            case 'views':
                $query->orderBy('view_count', 'desc');
                break;
            case 'year':
                $query->orderBy('release_year', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $videos = $query->paginate($perPage);

        return response()->json([
            'message' => 'Videos retrieved successfully',
            'videos' => $videos->items(),
            'pagination' => [
                'total' => $videos->total(),
                'per_page' => $videos->perPage(),
                'current_page' => $videos->currentPage(),
                'last_page' => $videos->lastPage(),
                'from' => $videos->firstItem(),
                'to' => $videos->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created video.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:movie,series,anime',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|string|max:500',
            'banner' => 'nullable|string|max:500',
            'cloudflare_video_uid' => 'nullable|string|max:255',
            'magnet_url' => 'nullable|string', // For movies only
            'duration' => 'nullable|integer|min:0',
            'release_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 5),
            'age_rating' => 'nullable|string|max:10',
            'imdb_rating' => 'nullable|numeric|min:0|max:10',
            'is_published' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'is_trending' => 'nullable|boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'genre_ids' => 'nullable|array',
            'genre_ids.*' => 'exists:genres,id',
        ]);

        // Extract relationship data and magnet URL
        $categoryIds = $validated['category_ids'] ?? [];
        $genreIds = $validated['genre_ids'] ?? [];
        $magnetUrl = $validated['magnet_url'] ?? null;
        unset($validated['category_ids'], $validated['genre_ids'], $validated['magnet_url']);

        // Set defaults
        $validated['view_count'] = 0;
        $validated['is_published'] = $validated['is_published'] ?? false;
        $validated['is_featured'] = $validated['is_featured'] ?? false;
        $validated['is_trending'] = $validated['is_trending'] ?? false;

        // For movies with magnet URL, initiate upload in background
        if ($validated['type'] === 'movie' && $magnetUrl) {
            $torrentService = app(\App\Services\TorrentToStreamService::class);

            $result = $torrentService->uploadFromMagnet($magnetUrl, [
                'name' => $validated['title'],
            ]);

            if ($result['success']) {
                // Store transfer ID temporarily (will be updated by background job with actual UID)
                $validated['cloudflare_video_uid'] = 'processing_transfer_' . $result['transfer_id'];
            } else {
                return response()->json([
                    'message' => 'Failed to initiate video upload',
                    'error' => $result['message'] ?? 'Unknown error',
                ], 500);
            }
        }

        // For movies without magnet URL, cloudflare_video_uid should be provided
        if ($validated['type'] === 'movie' && empty($validated['cloudflare_video_uid'])) {
            return response()->json([
                'message' => 'Either cloudflare_video_uid or magnet_url is required for movies',
            ], 400);
        }

        $video = Video::create($validated);

        // Attach categories and genres
        if (!empty($categoryIds)) {
            $video->categories()->attach($categoryIds);
        }
        if (!empty($genreIds)) {
            $video->genres()->attach($genreIds);
        }

        // Load relationships for response
        $video->load(['categories', 'genres']);

        return response()->json([
            'message' => 'Video created successfully' . ($magnetUrl ? '. Upload processing in background.' : ''),
            'video' => $video,
        ], 201);
    }

    /**
     * Display the specified video.
     */
    public function show($id)
    {
        $video = Video::with(['categories', 'genres', 'seasons.episodes'])
            ->find($id);

        if (!$video) {
            return response()->json([
                'message' => 'Video not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Video retrieved successfully',
            'video' => $video,
        ]);
    }

    /**
     * Update the specified video.
     */
    public function update(Request $request, $id)
    {
        $video = Video::find($id);

        if (!$video) {
            return response()->json([
                'message' => 'Video not found',
            ], 404);
        }

        $validated = $request->validate([
            'type' => 'sometimes|in:movie,series,anime',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|string|max:500',
            'banner' => 'nullable|string|max:500',
            'cloudflare_video_uid' => 'nullable|string|max:255',
            'duration' => 'nullable|integer|min:0',
            'release_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 5),
            'age_rating' => 'nullable|string|max:10',
            'imdb_rating' => 'nullable|numeric|min:0|max:10',
            'is_published' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'is_trending' => 'nullable|boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'genre_ids' => 'nullable|array',
            'genre_ids.*' => 'exists:genres,id',
        ]);

        // Extract relationship data
        $categoryIds = $validated['category_ids'] ?? null;
        $genreIds = $validated['genre_ids'] ?? null;
        unset($validated['category_ids'], $validated['genre_ids']);

        $video->update($validated);

        // Sync categories and genres if provided
        if ($categoryIds !== null) {
            $video->categories()->sync($categoryIds);
        }
        if ($genreIds !== null) {
            $video->genres()->sync($genreIds);
        }

        // Load relationships for response
        $video->load(['categories', 'genres']);

        return response()->json([
            'message' => 'Video updated successfully',
            'video' => $video->fresh(['categories', 'genres']),
        ]);
    }

    /**
     * Remove the specified video.
     */
    public function destroy($id)
    {
        $video = Video::find($id);

        if (!$video) {
            return response()->json([
                'message' => 'Video not found',
            ], 404);
        }

        // Detach relationships
        $video->categories()->detach();
        $video->genres()->detach();

        // Delete related seasons and episodes (cascade will handle this if set in migration)
        $video->seasons()->delete();

        // Placeholder: In production, this would delete from Cloudflare Stream
        // CloudflareService::deleteVideo($video->cloudflare_video_uid);

        $video->delete();

        return response()->json([
            'message' => 'Video deleted successfully',
        ]);
    }

    /**
     * Increment view count for a video.
     */
    public function incrementView($id)
    {
        $video = Video::find($id);

        if (!$video) {
            return response()->json([
                'message' => 'Video not found',
            ], 404);
        }

        $video->increment('view_count');

        return response()->json([
            'message' => 'View count updated',
            'view_count' => $video->view_count,
        ]);
    }

    /**
     * Get featured videos.
     */
    public function featured()
    {
        $videos = Video::with(['categories', 'genres'])
            ->where('is_featured', true)
            ->where('is_published', true)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'message' => 'Featured videos retrieved successfully',
            'videos' => $videos,
        ]);
    }

    /**
     * Get trending videos.
     */
    public function trending()
    {
        $videos = Video::with(['categories', 'genres'])
            ->where('is_trending', true)
            ->where('is_published', true)
            ->orderBy('view_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'message' => 'Trending videos retrieved successfully',
            'videos' => $videos,
        ]);
    }
}
