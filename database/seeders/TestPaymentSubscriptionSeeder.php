<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestPaymentSubscriptionSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // Create or get users with IDs 7 and 8
    $user7 = User::firstOrCreate(
      ['id' => 7],
      [
        'name' => 'Test User 7',
        'phone' => '+9607770007',
        'role' => 'user',
        'is_active' => true,
      ]
    );

    $user8 = User::firstOrCreate(
      ['id' => 8],
      [
        'name' => 'Test User 8',
        'phone' => '+9607770008',
        'role' => 'user',
        'is_active' => true,
      ]
    );

    DB::table('subscriptions')->insert([
      [
        'user_id' => $user7->id,
        'plan_type' => 'monthly',
        'amount' => 9.99,
        'currency' => 'MVR',
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-31',
        'status' => 'active',
        'auto_renew' => true,
        'created_at' => Carbon::now()->subDays(10),
        'updated_at' => Carbon::now(),
      ],
      [
        'user_id' => $user8->id,
        'plan_type' => 'annual',
        'amount' => 19.99,
        'currency' => 'MVR',
        'start_date' => '2024-12-01',
        'end_date' => '2024-12-31',
        'status' => 'expired',
        'auto_renew' => false,
        'created_at' => Carbon::now()->subMonths(2),
        'updated_at' => Carbon::now()->subMonth(),
      ],

    ]);
  }
}
