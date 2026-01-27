<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
  use WithoutModelEvents;

  /**
   * Seed the application's database.
   */
  public function run(): void
  {
    // Seed admin and test users first
    $this->call(AdminUserSeeder::class);

    // Seed test data for development/testing
    $this->call(TestPaymentSubscriptionSeeder::class);
    $this->call(TestPaymentSeeder::class);
  }
}
