<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds phone authentication fields to users table
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add phone number field
            $table->string('phone')->unique()->nullable()->after('email');
            
            // Track if phone is verified
            $table->boolean('phone_verified')->default(false)->after('phone');
            
            // Make email and password optional (since we're using phone OTP)
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'phone_verified']);
            
            // Restore email and password as required
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
