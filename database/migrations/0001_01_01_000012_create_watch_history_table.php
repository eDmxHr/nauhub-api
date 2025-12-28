<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Creates watch_history table - tracks user viewing progress for both movies and episodes
   */
  public function up(): void
  {
    Schema::create('watch_history', function (Blueprint $table) {
      $table->id();

      // User and profile
      $table->foreignId('user_id')->constrained()->onDelete('cascade');
      $table->foreignId('profile_id')->nullable()->constrained()->onDelete('cascade');

      // Polymorphic relationship: can track movies (video) OR episodes
      // For movies: video_id is set, episode_id is null
      // For series/anime: episode_id is set, video_id points to parent series
      $table->foreignId('video_id')->constrained()->onDelete('cascade'); // Parent video (movie or series)
      $table->foreignId('episode_id')->nullable()->constrained()->onDelete('cascade'); // Specific episode (for series)

      // Progress tracking
      $table->integer('progress_seconds')->default(0); // How far the user watched
      $table->integer('duration_seconds')->nullable(); // Total duration (cached for performance)
      $table->boolean('completed')->default(false); // Watched to the end (>90%)

      // Timestamps
      $table->timestamp('last_watched_at')->useCurrent(); // Last time this was played
      $table->timestamps();

      // Ensure one watch record per user/profile per video/episode
      $table->unique(['user_id', 'profile_id', 'video_id', 'episode_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('watch_history');
  }
};
