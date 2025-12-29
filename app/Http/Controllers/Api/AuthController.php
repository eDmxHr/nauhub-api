<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OtpVerification;
use Illuminate\Http\Request;

class AuthController extends Controller
{
  /**
   * Admin login with email/password
   * POST /api/auth/login
   */
  public function login(Request $request)
  {
    $validated = $request->validate([
      'email' => 'required|email',
      'password' => 'required|string',
    ]);

    $user = User::where('email', $validated['email'])->first();

    if (!$user || !\Hash::check($validated['password'], $user->password)) {
      return response()->json([
        'message' => 'Invalid credentials',
      ], 401);
    }

    // Create token
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
      'access_token' => $token,
      'token_type' => 'bearer',
      'user' => $user,
    ]);
  }

  /**
   * Send OTP to phone number
   * POST /api/auth/send-otp
   *
   * Request body: { "phone": "1234567" }
   */
  public function sendOtp(Request $request)
  {
    // Validate phone number (exactly 7 digits)
    $validated = $request->validate([
      'phone' => 'required|string|regex:/^[0-9]{7}$/',
    ]);

    // For development: Use fixed OTP 123456
    $otp = '123456';

    // Store OTP in database (expires in 5 minutes)
    OtpVerification::create([
      'phone' => $validated['phone'],
      'otp' => $otp,
      'expires_at' => now()->addMinutes(5),
      'is_verified' => false,
    ]);

    // TODO: In production, integrate SMS service (Twilio, Africa's Talking, etc.)
    // Example: SMS::send($validated['phone'], "Your OTP is: $otp");

    return response()->json([
      'message' => 'OTP sent successfully',
      'otp' => $otp, // ONLY FOR DEVELOPMENT - Remove in production
      'expires_in' => '5 minutes',
    ], 200);
  }

  /**
   * Verify OTP and login/register user
   * POST /api/auth/verify-otp
   * 
   * Request body: { "phone": "1234567", "otp": "123456", "name": "John Doe" }
   * Note: name is optional, only needed for first-time registration
   */
  public function verifyOtp(Request $request)
  {
    // Validate input
    $validated = $request->validate([
      'phone' => 'required|string|regex:/^[0-9]{7}$/',
      'otp' => 'required|string|size:6',
      'name' => 'nullable|string|max:255', // Only required for new users
    ]);

    // Find the most recent valid OTP for this phone number
    $otpRecord = OtpVerification::where('phone', $validated['phone'])
      ->where('otp', $validated['otp'])
      ->where('expires_at', '>', now()) // Not expired
      ->where('is_verified', false) // Not already used
      ->latest()
      ->first();

    // Check if OTP is valid
    if (!$otpRecord) {
      return response()->json([
        'message' => 'Invalid or expired OTP',
      ], 401);
    }

    // Mark OTP as verified (prevent reuse)
    $otpRecord->update(['is_verified' => true]);

    // Find existing user or create new one
    $user = User::where('phone', $validated['phone'])->first();

    if ($user) {
      // Existing user - just verify phone if not already verified
      if (!$user->phone_verified) {
        $user->update(['phone_verified' => true]);
      }
      $isNewUser = false;
    } else {
      // New user - create account
      $user = User::create([
        'phone' => $validated['phone'],
        'name' => $validated['name'] ?? 'User', // Default name if not provided
        'phone_verified' => true,
        'role' => 'user', // Default role
        'is_subscribed' => false, // Default subscription status
      ]);
      $isNewUser = true;
    }

    // Generate Sanctum authentication token
    $token = $user->createToken('auth-token')->plainTextToken;

    return response()->json([
      'message' => $isNewUser ? 'Registration successful' : 'Login successful',
      'user' => $user,
      'token' => $token,
      'is_new_user' => $isNewUser,
    ], 200);
  }

  /**
   * Logout user (revoke current access token)
   * POST /api/auth/logout
   * 
   * Headers: Authorization: Bearer {token}
   */
  public function logout(Request $request)
  {
    // Revoke all tokens for the user
    $request->user()->tokens()->delete();

    return response()->json([
      'message' => 'Logged out successfully',
    ], 200);
  }

  /**
   * Get authenticated user details
   * GET /api/auth/user
   * 
   * Headers: Authorization: Bearer {token}
   */
  public function user(Request $request)
  {
    return response()->json([
      'user' => $request->user(),
    ], 200);
  }
}
