<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GenreController extends Controller
{
    /**
     * Display a listing of genres.
     */
    public function index()
    {
        $genres = Genre::withCount('videos')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Genres retrieved successfully',
            'genres' => $genres,
        ]);
    }

    /**
     * Store a newly created genre.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:genres,name',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'nullable|boolean',
        ]);

        // Auto-generate slug from name
        $validated['slug'] = Str::slug($validated['name']);

        // Set defaults
        $validated['is_active'] = $validated['is_active'] ?? true;

        $genre = Genre::create($validated);

        return response()->json([
            'message' => 'Genre created successfully',
            'genre' => $genre,
        ], 201);
    }

    /**
     * Display the specified genre.
     */
    public function show($id)
    {
        $genre = Genre::withCount('videos')->find($id);

        if (!$genre) {
            return response()->json([
                'message' => 'Genre not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Genre retrieved successfully',
            'genre' => $genre,
        ]);
    }

    /**
     * Update the specified genre.
     */
    public function update(Request $request, $id)
    {
        $genre = Genre::find($id);

        if (!$genre) {
            return response()->json([
                'message' => 'Genre not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:genres,name,' . $id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'nullable|boolean',
        ]);

        // Update slug if name changed
        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $genre->update($validated);

        return response()->json([
            'message' => 'Genre updated successfully',
            'genre' => $genre->fresh(),
        ]);
    }

    /**
     * Remove the specified genre.
     */
    public function destroy($id)
    {
        $genre = Genre::withCount('videos')->find($id);

        if (!$genre) {
            return response()->json([
                'message' => 'Genre not found',
            ], 404);
        }

        // Check if genre has videos
        if ($genre->videos_count > 0) {
            return response()->json([
                'message' => 'Cannot delete genre with associated videos. Remove videos first.',
            ], 400);
        }

        $genre->delete();

        return response()->json([
            'message' => 'Genre deleted successfully',
        ]);
    }
}
