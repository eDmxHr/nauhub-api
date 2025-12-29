<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Get all profiles for the authenticated user
     * GET /api/profiles
     */
    public function index(Request $request)
    {
        // Get all profiles belonging to the authenticated user
        $profiles = $request->user()->profiles;

        return response()->json([
            'profiles' => $profiles,
        ]);
    }

    /**
     * Create a new profile
     * POST /api/profiles
     * 
     * Request body: {
     *   "name": "John's Profile",
     *   "avatar": "avatar_url",
     *   "pin": "1234",
     *   "is_kids_profile": false
     * }
     */
    public function store(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'avatar' => 'nullable|string',
            'pin' => 'nullable|string|size:4', // 4-digit PIN
            'is_kids_profile' => 'nullable|boolean',
        ]);

        // Check if user already has 5 profiles (Netflix-like limit)
        if ($request->user()->profiles()->count() >= 5) {
            return response()->json([
                'message' => 'Maximum number of profiles (5) reached',
            ], 400);
        }

        // Hash PIN if provided
        if (isset($validated['pin'])) {
            $validated['pin'] = Hash::make($validated['pin']);
        }

        // Create profile for authenticated user
        $profile = $request->user()->profiles()->create($validated);

        return response()->json([
            'message' => 'Profile created successfully',
            'profile' => $profile,
        ], 201);
    }

    /**
     * Get a specific profile
     * GET /api/profiles/{id}
     */
    public function show(Request $request, $id)
    {
        // Find profile and ensure it belongs to authenticated user
        $profile = $request->user()->profiles()->find($id);

        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found',
            ], 404);
        }

        return response()->json([
            'profile' => $profile,
        ]);
    }

    /**
     * Update a profile
     * PUT /api/profiles/{id}
     * 
     * Request body: {
     *   "name": "Updated Name",
     *   "avatar": "new_avatar_url",
     *   "is_kids_profile": true
     * }
     */
    public function update(Request $request, $id)
    {
        // Find profile and ensure it belongs to authenticated user
        $profile = $request->user()->profiles()->find($id);

        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found',
            ], 404);
        }

        // Validate input
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'avatar' => 'nullable|string',
            'pin' => 'nullable|string|size:4',
            'is_kids_profile' => 'nullable|boolean',
        ]);

        // Hash PIN if provided
        if (isset($validated['pin'])) {
            $validated['pin'] = Hash::make($validated['pin']);
        }

        // Update profile
        $profile->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $profile->fresh(), // Return updated profile
        ]);
    }

    /**
     * Delete a profile
     * DELETE /api/profiles/{id}
     */
    public function destroy(Request $request, $id)
    {
        // Find profile and ensure it belongs to authenticated user
        $profile = $request->user()->profiles()->find($id);

        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found',
            ], 404);
        }

        // Prevent deleting if it's the only profile
        if ($request->user()->profiles()->count() <= 1) {
            return response()->json([
                'message' => 'Cannot delete the last profile',
            ], 400);
        }

        // Delete profile
        $profile->delete();

        return response()->json([
            'message' => 'Profile deleted successfully',
        ]);
    }

    /**
     * Verify profile PIN
     * POST /api/profiles/{id}/verify-pin
     * 
     * Request body: { "pin": "1234" }
     */
    public function verifyPin(Request $request, $id)
    {
        // Find profile and ensure it belongs to authenticated user
        $profile = $request->user()->profiles()->find($id);

        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found',
            ], 404);
        }

        // Check if profile has a PIN
        if (!$profile->pin) {
            return response()->json([
                'message' => 'This profile does not have a PIN',
                'verified' => true, // No PIN means no verification needed
            ]);
        }

        // Validate PIN input
        $validated = $request->validate([
            'pin' => 'required|string|size:4',
        ]);

        // Verify PIN
        if (Hash::check($validated['pin'], $profile->pin)) {
            return response()->json([
                'message' => 'PIN verified successfully',
                'verified' => true,
                'profile' => $profile,
            ]);
        }

        return response()->json([
            'message' => 'Invalid PIN',
            'verified' => false,
        ], 401);
    }
}
