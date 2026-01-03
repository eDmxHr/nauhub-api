<?php

namespace Database\Seeders;

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
    DB::table('subscriptions')->insert([
      [
        'user_id' => 7,
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
        'user_id' => 8,
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
