<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Adds role field to users table to differentiate between regular users and admins
   */
  public function up(): void
  {
    Schema::table('users', function (Blueprint $table) {
      // Role: 'user' or 'admin' - determines access level
      $table->enum('role', ['user', 'admin'])->default('user')->after('email');

      // Subscription status - managed through subscriptions table
      $table->boolean('is_subscribed')->default(false)->after('role');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->dropColumn(['role', 'is_subscribed']);
    });
  }
};
