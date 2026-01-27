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
        // Create admin user (or update if exists)
        $admin = User::updateOrCreate(
            ['phone' => '9999999'],
            [
                'name' => 'Admin User',
                'email' => 'admin@nauhub.com',
                'phone_verified' => true,
                'role' => 'admin',
                'is_subscribed' => true,
                'is_active' => true,
                'password' => Hash::make('password'), // Default password for admin
            ]
        );

        // Create regular test user (or update if exists)
        $user = User::updateOrCreate(
            ['phone' => '1234567'],
            [
                'name' => 'Test User',
                'email' => 'user@nauhub.com',
                'phone_verified' => true,
                'role' => 'user',
                'is_subscribed' => false,
                'is_active' => true,
                'password' => Hash::make('password'), // Default password for user
            ]
        );

        $this->command->info('✅ Admin and test users created successfully!');
        $this->command->info('📱 Admin - Phone: 9999999 | Email: admin@nauhub.com | Password: password');
        $this->command->info('📱 User - Phone: 1234567 | Email: user@nauhub.com | Password: password');
    }
}
