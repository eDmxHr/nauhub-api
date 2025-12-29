<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first non-admin user (or create one if needed)
        $user = User::where('role', 'user')->first();

        if (!$user) {
            $user = User::create([
                'phone' => '+9607777777',
                'name' => 'Test User',
                'role' => 'user',
            ]);
        }

        // Create an active Standard Monthly subscription
        $activeSubscription = Subscription::create([
            'user_id' => $user->id,
            'plan_type' => 'monthly',
            'amount' => 149.00,
            'currency' => 'MVR',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(20),
            'status' => 'active',
            'auto_renew' => false,
        ]);

        // Create payment for active subscription
        Payment::create([
            'user_id' => $user->id,
            'subscription_id' => $activeSubscription->id,
            'amount' => 149.00,
            'currency' => 'MVR',
            'reference_code' => 'PAY-' . strtoupper(Str::random(10)),
            'proof_image' => 'payment_proofs/sample.jpg',
            'status' => 'approved',
            'verified_by' => 1, // Admin user
            'verified_at' => now()->subDays(10),
            'admin_notes' => 'Payment verified and approved',
        ]);

        // Create a pending Premium Annual subscription
        $pendingSubscription = Subscription::create([
            'user_id' => $user->id,
            'plan_type' => 'annual',
            'amount' => 1990.00,
            'currency' => 'MVR',
            'start_date' => now(),
            'end_date' => now()->addDays(365),
            'status' => 'pending',
            'auto_renew' => false,
        ]);

        // Create pending payment
        Payment::create([
            'user_id' => $user->id,
            'subscription_id' => $pendingSubscription->id,
            'amount' => 1990.00,
            'currency' => 'MVR',
            'reference_code' => 'PAY-' . strtoupper(Str::random(10)),
            'proof_image' => 'payment_proofs/sample_pending.jpg',
            'status' => 'pending',
        ]);

        // Create an expired Basic subscription
        $expiredSubscription = Subscription::create([
            'user_id' => $user->id,
            'plan_type' => 'monthly',
            'amount' => 99.00,
            'currency' => 'MVR',
            'start_date' => now()->subDays(60),
            'end_date' => now()->subDays(30),
            'status' => 'expired',
            'auto_renew' => false,
        ]);

        // Create payment for expired subscription
        Payment::create([
            'user_id' => $user->id,
            'subscription_id' => $expiredSubscription->id,
            'amount' => 99.00,
            'currency' => 'MVR',
            'reference_code' => 'PAY-' . strtoupper(Str::random(10)),
            'proof_image' => 'payment_proofs/sample_old.jpg',
            'status' => 'approved',
            'verified_by' => 1, // Admin user
            'verified_at' => now()->subDays(60),
            'admin_notes' => 'Payment verified',
        ]);

        echo "✅ Subscription seeder completed!\n";
        echo "   - Created 3 subscriptions (active, pending, expired)\n";
        echo "   - Created 3 payment records\n";
        echo "   - User ID: {$user->id}\n";
    }
}