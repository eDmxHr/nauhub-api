<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Creates genres table - for content classification (Action, Drama, Comedy, etc.)
   */
  public function up(): void
  {
    Schema::create('genres', function (Blueprint $table) {
      $table->id();

      // Genre details
      $table->string('name'); // e.g., "Action", "Drama", "Comedy", "Sci-Fi", "Anime"
      $table->string('slug')->unique(); // URL-friendly: "action", "drama", "sci-fi"
      $table->text('description')->nullable();

      // Optional icon or color for UI display
      $table->string('icon')->nullable();
      $table->string('color')->nullable(); // Hex color code

      $table->boolean('is_active')->default(true);

      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('genres');
  }
};
