<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Creates videos table - parent table for all content (movies, series, anime)
   */
  public function up(): void
  {
    Schema::create('videos', function (Blueprint $table) {
      $table->id();

      // Content type: 'movie', 'series', or 'anime'
      $table->enum('type', ['movie', 'series', 'anime']);

      // Basic information
      $table->string('title');
      $table->text('description')->nullable();
      $table->string('thumbnail')->nullable(); // Cover image URL
      $table->string('banner')->nullable(); // Large banner image for featured content

      // For movies: store Cloudflare Stream video UID directly
      // For series/anime: this will be null (video UIDs are in episodes table)
      $table->string('cloudflare_video_uid')->nullable();

      // Video metadata
      $table->integer('duration')->nullable(); // Duration in seconds (for movies only)
      $table->integer('release_year')->nullable();
      $table->string('rating')->nullable(); // e.g., "PG-13", "R", "TV-MA"
      $table->decimal('imdb_rating', 3, 1)->nullable(); // e.g., 8.5

      // Status and visibility
      $table->boolean('is_published')->default(true); // Show to users or hide
      $table->boolean('is_featured')->default(false); // Show in featured carousel
      $table->boolean('is_trending')->default(false); // Show in trending section

      // View counter
      $table->bigInteger('view_count')->default(0);

      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('videos');
  }
};
