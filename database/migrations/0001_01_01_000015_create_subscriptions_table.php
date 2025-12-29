<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Creates subscriptions table - manages user subscription plans and status
   */
  public function up(): void
  {
    Schema::create('subscriptions', function (Blueprint $table) {
      $table->id();

      // User
      $table->foreignId('user_id')->constrained()->onDelete('cascade');

      // Plan details
      $table->enum('plan_type', ['monthly', 'annual']); // Subscription duration
      $table->decimal('amount', 10, 2); // Amount paid
      $table->string('currency')->default('MVR');

      // Subscription period
      $table->date('start_date');
      $table->date('end_date');

      // Status: pending (awaiting payment), active, expired, cancelled
      $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');

      // Auto-renewal (for future payment gateway integration)
      $table->boolean('auto_renew')->default(false);

      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('subscriptions');
  }
};
