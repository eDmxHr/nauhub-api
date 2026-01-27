<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestPaymentSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // Create or get admin user
    $admin = User::firstOrCreate(
      ['id' => 6],
      [
        'name' => 'Admin User',
        'phone' => '+9607770006',
        'role' => 'admin',
        'is_active' => true,
      ]
    );
    $adminId = $admin->id;

    // Get subscriptions created by TestPaymentSubscriptionSeeder
    $subscription1 = Subscription::where('user_id', 7)->where('plan_type', 'monthly')->first();
    $subscription2 = Subscription::where('user_id', 7)->where('plan_type', 'annual')->first();
    $subscription3 = Subscription::where('user_id', 8)->where('plan_type', 'annual')->first();
    
    // If subscriptions don't exist, create them
    if (!$subscription1) {
      $subscription1 = Subscription::create([
        'user_id' => 7,
        'plan_type' => 'monthly',
        'amount' => 9.99,
        'currency' => 'MVR',
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-31',
        'status' => 'active',
        'auto_renew' => true,
      ]);
    }
    
    if (!$subscription2) {
      $subscription2 = Subscription::create([
        'user_id' => 7,
        'plan_type' => 'annual',
        'amount' => 19.99,
        'currency' => 'MVR',
        'start_date' => '2024-12-01',
        'end_date' => '2024-12-31',
        'status' => 'active',
        'auto_renew' => false,
      ]);
    }
    
    if (!$subscription3) {
      $subscription3 = Subscription::create([
        'user_id' => 8,
        'plan_type' => 'annual',
        'amount' => 49.99,
        'currency' => 'MVR',
        'start_date' => '2024-11-01',
        'end_date' => '2024-12-31',
        'status' => 'expired',
        'auto_renew' => false,
      ]);
    }

    DB::table('payments')->insert([
      [
        'user_id' => 7,
        'subscription_id' => 15,
        'amount' => 9.99,
        'currency' => 'MVR',
        'reference_code' => 'PAY-0001',
        'proof_image' => 'payments/proof_0001.jpg',
        'status' => 'pending',
        'admin_notes' => null,
        'verified_by' => null,
        'verified_at' => null,
        'created_at' => Carbon::now()->subDays(3),
        'updated_at' => Carbon::now()->subDays(3),
      ],
      [
        'user_id' => 7,
        'subscription_id' => 16,
        'amount' => 19.99,
        'currency' => 'MVR',
        'reference_code' => 'PAY-0002',
        'proof_image' => 'payments/proof_0002.jpg',
        'status' => 'approved',
        'admin_notes' => 'Payment verified successfully',
        'verified_by' => $adminId,
        'verified_at' => Carbon::now()->subDays(1),
        'created_at' => Carbon::now()->subDays(2),
        'updated_at' => Carbon::now()->subDays(1),
      ],
      [
        'user_id' => 8,
        'subscription_id' => 17,
        'amount' => 49.99,
        'currency' => 'MVR',
        'reference_code' => 'PAY-0003',
        'proof_image' => 'payments/proof_0003.jpg',
        'status' => 'approved',
        'admin_notes' => 'Bank transfer confirmed',
        'verified_by' => $adminId,
        'verified_at' => Carbon::now()->subHours(12),
        'created_at' => Carbon::now()->subDays(1),
        'updated_at' => Carbon::now()->subHours(12),
      ],
      [
        'user_id' => 8,
        'subscription_id' => 18,
        'amount' => 199.00,
        'currency' => 'MVR',
        'reference_code' => 'PAY-0004',
        'proof_image' => null,
        'status' => 'rejected',
        'admin_notes' => 'Invalid payment proof',
        'verified_by' => $adminId,
        'verified_at' => Carbon::now()->subHours(6),
        'created_at' => Carbon::now()->subDays(1),
        'updated_at' => Carbon::now()->subHours(6),
      ],
      [
        'user_id' => 7,
        'subscription_id' => 19,
        'amount' => 9.99,
        'currency' => 'MVR',
        'reference_code' => 'PAY-0005',
        'proof_image' => 'payments/proof_0005.jpg',
        'status' => 'pending',
        'admin_notes' => null,
        'verified_by' => null,
        'verified_at' => null,
        'created_at' => Carbon::now()->subHours(4),
        'updated_at' => Carbon::now()->subHours(4),
      ],
      [
        'user_id' => 8,
        'subscription_id' => 20,
        'amount' => 19.99,
        'currency' => 'MVR',
        'reference_code' => 'PAY-0006',
        'proof_image' => 'payments/proof_0006.jpg',
        'status' => 'approved',
        'admin_notes' => 'Manual verification completed',
        'verified_by' => $adminId,
        'verified_at' => Carbon::now()->subHours(2),
        'created_at' => Carbon::now()->subHours(3),
        'updated_at' => Carbon::now()->subHours(2),
      ],
    ]);
  }
}
