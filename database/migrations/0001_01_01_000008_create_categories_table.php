<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Creates categories table - for organizing content (Featured, Trending, New Releases, etc.)
   */
  public function up(): void
  {
    Schema::create('categories', function (Blueprint $table) {
      $table->id();

      // Category details
      $table->string('name'); // e.g., "Featured", "Trending", "New Releases", "Action Movies"
      $table->string('slug')->unique(); // URL-friendly version: "featured", "trending", "new-releases"
      $table->text('description')->nullable();

      // Display order on frontend (lower numbers appear first)
      $table->integer('sort_order')->default(0);

      // Visibility
      $table->boolean('is_active')->default(true);

      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('categories');
  }
};
