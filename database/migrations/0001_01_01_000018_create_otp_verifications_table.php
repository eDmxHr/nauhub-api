<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates OTP verifications table for phone authentication
     */
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            
            // Phone number that requested OTP
            $table->string('phone');
            
            // 6-digit OTP code
            $table->string('otp', 6);
            
            // OTP expiration time (5 minutes from creation)
            $table->timestamp('expires_at');
            
            // Track if OTP has been used
            $table->boolean('is_verified')->default(false);
            
            $table->timestamps();
            
            // Index for faster lookups
            $table->index(['phone', 'otp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
