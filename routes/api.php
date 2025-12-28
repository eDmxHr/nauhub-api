<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
| These routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
| All routes here are prefixed with /api
|
*/

// Authentication routes (public - no auth required)
Route::prefix('auth')->group(function () {
    // Send OTP to phone number
    // POST /api/auth/send-otp
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    
    // Verify OTP and login/register
    // POST /api/auth/verify-otp
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
});

// Protected routes (require authentication token)
Route::middleware('auth:sanctum')->group(function () {
    // Get authenticated user
    // GET /api/auth/user
    Route::get('/auth/user', [AuthController::class, 'user']);
    
    // Logout
    // POST /api/auth/logout
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    
    // TODO: Add more protected routes here
    // - Videos CRUD
    // - Profiles
    // - Watch history
    // - Playlists
    // - Subscriptions
    // etc.
});
