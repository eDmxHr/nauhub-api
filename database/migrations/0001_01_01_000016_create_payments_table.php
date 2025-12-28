<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Creates payments table - tracks payment submissions and manual verification
   */
  public function up(): void
  {
    Schema::create('payments', function (Blueprint $table) {
      $table->id();

      // User and subscription
      $table->foreignId('user_id')->constrained()->onDelete('cascade');
      $table->foreignId('subscription_id')->nullable()->constrained()->onDelete('set null');

      // Payment details
      $table->decimal('amount', 10, 2);
      $table->string('currency')->default('USD');
      $table->string('reference_code')->unique(); // Unique payment reference for user

      // Payment proof (uploaded screenshot/document)
      $table->string('proof_image')->nullable(); // Path to uploaded payment proof

      // Verification
      $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
      $table->text('admin_notes')->nullable(); // Admin can add notes when verifying
      $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null'); // Admin who verified
      $table->timestamp('verified_at')->nullable();

      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('payments');
  }
};
