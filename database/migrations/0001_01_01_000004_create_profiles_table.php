<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Creates profiles table - each user can have multiple profiles (like Netflix)
   */
  public function up(): void
  {
    Schema::create('profiles', function (Blueprint $table) {
      $table->id();

      // Foreign key to users table
      $table->foreignId('user_id')->constrained()->onDelete('cascade');

      // Profile details
      $table->string('name'); // Profile name (e.g., "John", "Kids", "Guest")
      $table->string('avatar')->nullable(); // Avatar image URL or identifier

      // PIN lock for profile protection (optional, for parental controls)
      $table->string('pin')->nullable(); // 4-digit PIN, hashed
      $table->boolean('is_kids_profile')->default(false); // Restrict content for kids

      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('profiles');
  }
};
