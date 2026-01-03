<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_id')->unique(); // e.g., "basic_monthly"
            $table->enum('tier', ['basic', 'standard', 'premium']);
            $table->string('name', 100); // e.g., "Standard - Monthly"
            $table->enum('duration', ['monthly', 'annual']);
            $table->decimal('price', 10, 2);
            $table->string('currency', 10)->default('MVR');
            $table->integer('duration_days'); // 30 or 365
            $table->json('features'); // Array of feature strings
            $table->boolean('is_popular')->default(false);
            $table->string('savings_text', 50)->nullable(); // e.g., "MVR 298"
            $table->string('discount_text', 50)->nullable(); // e.g., "2 months free!"
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
