<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\GenreController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\Api\SeasonController;
use App\Http\Controllers\Api\EpisodeController;
use App\Http\Controllers\Api\WatchHistoryController;
use App\Http\Controllers\Api\PlaylistController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\CloudflareController;

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
  // Admin login with email/password
  // POST /api/auth/login
  Route::post('/login', [AuthController::class, 'login']);

  // Send OTP to phone number
  // POST /api/auth/send-otp
  Route::post('/send-otp', [AuthController::class, 'sendOtp']);

  // Verify OTP and login/register
  // POST /api/auth/verify-otp
  Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
});

// Protected routes (require authentication token)
Route::middleware('auth:sanctum')->group(function () {

  // Auth routes
  Route::prefix('auth')->group(function () {
    // Get authenticated user
    // GET /api/auth/user
    Route::get('/user', [AuthController::class, 'user']);

    // Logout
    // POST /api/auth/logout
    Route::post('/logout', [AuthController::class, 'logout']);
  });

  // Profile routes
  Route::prefix('profiles')->group(function () {
    Route::get('/', [ProfileController::class, 'index']);
    Route::post('/', [ProfileController::class, 'store']);
    Route::get('/{id}', [ProfileController::class, 'show']);
    Route::put('/{id}', [ProfileController::class, 'update']);
    Route::delete('/{id}', [ProfileController::class, 'destroy']);
    Route::post('/{id}/verify-pin', [ProfileController::class, 'verifyPin']);
  });

  // Public video routes (for browsing)
  Route::prefix('videos')->group(function () {
    Route::get('/', [VideoController::class, 'index']); // Paginated listing with filters
    Route::get('/featured', [VideoController::class, 'featured']); // Featured videos
    Route::get('/trending', [VideoController::class, 'trending']); // Trending videos
    Route::get('/{id}', [VideoController::class, 'show']); // Single video details
    Route::post('/{id}/view', [VideoController::class, 'incrementView']); // Increment view count
  });

  // Public category routes
  Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']); // All categories
    Route::get('/{id}', [CategoryController::class, 'show']); // Single category
  });

  // Public genre routes
  Route::prefix('genres')->group(function () {
    Route::get('/', [GenreController::class, 'index']); // All genres
    Route::get('/{id}', [GenreController::class, 'show']); // Single genre
  });

  // Public season routes
  Route::prefix('videos/{videoId}/seasons')->group(function () {
    Route::get('/', [SeasonController::class, 'index']); // Get all seasons for a video
  });

  Route::prefix('seasons')->group(function () {
    Route::get('/{id}', [SeasonController::class, 'show']); // Get single season with episodes
  });

  // Public episode routes
  Route::prefix('seasons/{seasonId}/episodes')->group(function () {
    Route::get('/', [EpisodeController::class, 'index']); // Get all episodes for a season
  });

  Route::prefix('episodes')->group(function () {
    Route::get('/{id}', [EpisodeController::class, 'show']); // Get single episode
    Route::post('/{id}/view', [EpisodeController::class, 'incrementView']); // Increment episode view count
  });

  // Watch History routes
  Route::prefix('watch-history')->group(function () {
    Route::get('/', [WatchHistoryController::class, 'index']); // Get watch history for profile
    Route::get('/continue-watching', [WatchHistoryController::class, 'continueWatching']); // Continue watching
    Route::post('/update-progress', [WatchHistoryController::class, 'updateProgress']); // Update playback progress
    Route::post('/resume-point', [WatchHistoryController::class, 'getResumePoint']); // Get resume point
    Route::post('/mark-completed', [WatchHistoryController::class, 'markAsCompleted']); // Mark as completed
    Route::delete('/remove', [WatchHistoryController::class, 'remove']); // Remove item
    Route::delete('/clear-all', [WatchHistoryController::class, 'clearAll']); // Clear all history
  });

  // Playlist routes (My List)
  Route::prefix('playlists')->group(function () {
    Route::get('/', [PlaylistController::class, 'index']); // Get all playlists for profile
    Route::post('/', [PlaylistController::class, 'store']); // Create custom playlist
    Route::get('/my-list', [PlaylistController::class, 'getMyList']); // Get "My List"
    Route::post('/my-list/add', [PlaylistController::class, 'addToMyList']); // Add video to My List
    Route::delete('/my-list/remove', [PlaylistController::class, 'removeFromMyList']); // Remove from My List
    Route::post('/my-list/check', [PlaylistController::class, 'isInMyList']); // Check if video is in My List
    Route::get('/{id}', [PlaylistController::class, 'show']); // Get single playlist with videos
    Route::put('/{id}', [PlaylistController::class, 'update']); // Update custom playlist
    Route::delete('/{id}', [PlaylistController::class, 'destroy']); // Delete custom playlist
    Route::post('/{id}/videos', [PlaylistController::class, 'addVideo']); // Add video to custom playlist
    Route::delete('/{id}/videos', [PlaylistController::class, 'removeVideo']); // Remove video from custom playlist
  });

  // Subscription routes (user)
  Route::prefix('subscriptions')->group(function () {
    Route::get('/plans', [SubscriptionController::class, 'plans']); // Get all subscription plans
    Route::get('/status', [SubscriptionController::class, 'status']); // Get current user subscription status
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe']); // Subscribe with payment proof
    Route::get('/history', [SubscriptionController::class, 'history']); // Get subscription history
    Route::post('/cancel', [SubscriptionController::class, 'cancel']); // Cancel active subscription
  });

  // Cloudflare Stream routes (user)
  Route::prefix('stream')->group(function () {
    Route::get('/{videoUid}', [CloudflareController::class, 'getStreamUrl']); // Get streaming URLs for a video
  });
});

// Admin routes (require authentication + admin role)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

  // Admin: Categories management
  Route::prefix('categories')->group(function () {
    Route::post('/', [CategoryController::class, 'store']);
    Route::put('/{id}', [CategoryController::class, 'update']);
    Route::delete('/{id}', [CategoryController::class, 'destroy']);
  });

  // Admin: Genres management
  Route::prefix('genres')->group(function () {
    Route::post('/', [GenreController::class, 'store']);
    Route::put('/{id}', [GenreController::class, 'update']);
    Route::delete('/{id}', [GenreController::class, 'destroy']);
  });

  // Admin: Videos management
  Route::prefix('videos')->group(function () {
    Route::post('/', [VideoController::class, 'store']);
    Route::put('/{id}', [VideoController::class, 'update']);
    Route::delete('/{id}', [VideoController::class, 'destroy']);
  });

  // Admin: Seasons management
  Route::prefix('videos/{videoId}/seasons')->group(function () {
    Route::post('/', [SeasonController::class, 'store']); // Create season for a video
  });

  Route::prefix('seasons')->group(function () {
    Route::put('/{id}', [SeasonController::class, 'update']); // Update season
    Route::delete('/{id}', [SeasonController::class, 'destroy']); // Delete season
  });

  // Admin: Episodes management
  Route::prefix('seasons/{seasonId}/episodes')->group(function () {
    Route::post('/', [EpisodeController::class, 'store']); // Create episode for a season
  });

  Route::prefix('episodes')->group(function () {
    Route::put('/{id}', [EpisodeController::class, 'update']); // Update episode
    Route::delete('/{id}', [EpisodeController::class, 'destroy']); // Delete episode
  });

  // Admin: Subscription & Payment management
  Route::prefix('subscriptions')->group(function () {
    Route::get('/all', [SubscriptionController::class, 'allSubscriptions']); // Get all subscriptions (with status filter)
  });

  Route::prefix('payments')->group(function () {
    Route::get('/pending', [SubscriptionController::class, 'pendingPayments']); // Get pending payments
    Route::get('/all', [SubscriptionController::class, 'allPayments']); // Get all payments (with status filter)
    Route::post('/{id}/approve', [SubscriptionController::class, 'approvePayment']); // Approve payment
    Route::post('/{id}/reject', [SubscriptionController::class, 'rejectPayment']); // Reject payment
  });

  // Admin: Cloudflare Stream & Put.io management
  Route::prefix('cloudflare')->group(function () {
    Route::get('/test-connection', [CloudflareController::class, 'testConnection']); // Test Cloudflare API connection
    Route::post('/upload-video', [CloudflareController::class, 'uploadVideo']); // Upload video file directly
    Route::post('/upload-from-url', [CloudflareController::class, 'uploadFromUrl']); // Upload from any direct URL
    Route::post('/upload-from-magnet', [CloudflareController::class, 'uploadFromMagnet']); // Step 1: Add magnet to Put.io (returns immediately)
    Route::get('/transfer/{transferId}/status', [CloudflareController::class, 'checkTransferStatus']); // Step 2: Check Put.io download status
    Route::post('/complete-upload/{transferId}', [CloudflareController::class, 'completeUpload']); // Step 3: Upload to Cloudflare when Put.io is done
    Route::get('/video/{videoUid}', [CloudflareController::class, 'getVideo']); // Get video details
    Route::get('/video/{videoUid}/status', [CloudflareController::class, 'getUploadStatus']); // Get upload status
    Route::delete('/video/{videoUid}', [CloudflareController::class, 'deleteVideo']); // Delete video
  });
});
