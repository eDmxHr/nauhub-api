<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'phone' => '9999999',
            'phone_verified' => true,
            'role' => 'admin',
            'is_subscribed' => true,
        ]);

        // Create regular test user
        User::create([
            'name' => 'Test User',
            'phone' => '1234567',
            'phone_verified' => true,
            'role' => 'user',
            'is_subscribed' => true,
        ]);

        $this->command->info('Admin and test users created successfully!');
        $this->command->info('Admin phone: 9999999 | OTP: 123456');
        $this->command->info('User phone: 1234567 | OTP: 123456');
    }
}
