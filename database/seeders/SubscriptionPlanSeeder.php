<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            // Basic Plan
            [
                'plan_id' => 'basic_monthly',
                'tier' => 'basic',
                'name' => 'Basic - Monthly',
                'duration' => 'monthly',
                'price' => 99.00,
                'currency' => 'MVR',
                'duration_days' => 30,
                'features' => [
                    '1 screen at a time',
                    '1 profile',
                    'Standard content library access',
                ],
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'plan_id' => 'basic_annual',
                'tier' => 'basic',
                'name' => 'Basic - Annual',
                'duration' => 'annual',
                'price' => 990.00,
                'currency' => 'MVR',
                'duration_days' => 365,
                'features' => [
                    '1 screen at a time',
                    '1 profile',
                    'Standard content library access',
                ],
                'is_popular' => false,
                'savings_text' => 'MVR 198',
                'discount_text' => '2 months free!',
                'is_active' => true,
                'sort_order' => 2,
            ],

            // Standard Plan (Most Popular)
            [
                'plan_id' => 'standard_monthly',
                'tier' => 'standard',
                'name' => 'Standard - Monthly',
                'duration' => 'monthly',
                'price' => 149.00,
                'currency' => 'MVR',
                'duration_days' => 30,
                'features' => [
                    '2 screens simultaneously',
                    'Up to 3 profiles',
                    'Full content library',
                ],
                'is_popular' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'plan_id' => 'standard_annual',
                'tier' => 'standard',
                'name' => 'Standard - Annual',
                'duration' => 'annual',
                'price' => 1490.00,
                'currency' => 'MVR',
                'duration_days' => 365,
                'features' => [
                    '2 screens simultaneously',
                    'Up to 3 profiles',
                    'Full content library',
                ],
                'is_popular' => true,
                'savings_text' => 'MVR 298',
                'discount_text' => '2 months free!',
                'is_active' => true,
                'sort_order' => 4,
            ],

            // Premium Plan
            [
                'plan_id' => 'premium_monthly',
                'tier' => 'premium',
                'name' => 'Premium - Monthly',
                'duration' => 'monthly',
                'price' => 199.00,
                'currency' => 'MVR',
                'duration_days' => 30,
                'features' => [
                    '4 screens simultaneously',
                    'Up to 5 profiles',
                    'Full content library',
                ],
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'plan_id' => 'premium_annual',
                'tier' => 'premium',
                'name' => 'Premium - Annual',
                'duration' => 'annual',
                'price' => 1990.00,
                'currency' => 'MVR',
                'duration_days' => 365,
                'features' => [
                    '4 screens simultaneously',
                    'Up to 5 profiles',
                    'Full content library',
                ],
                'is_popular' => false,
                'savings_text' => 'MVR 398',
                'discount_text' => '2 months free!',
                'is_active' => true,
                'sort_order' => 6,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
