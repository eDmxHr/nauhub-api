<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Creates episodes table - contains actual video files for series/anime
   */
  public function up(): void
  {
    Schema::create('episodes', function (Blueprint $table) {
      $table->id();

      // Foreign key to seasons table
      $table->foreignId('season_id')->constrained()->onDelete('cascade');

      // Episode details
      $table->integer('episode_number'); // Episode 1, 2, 3, etc.
      $table->string('title');
      $table->text('description')->nullable();
      $table->string('thumbnail')->nullable(); // Episode thumbnail/screenshot

      // Cloudflare Stream video UID for this episode
      $table->string('cloudflare_video_uid')->nullable();

      // Video metadata
      $table->integer('duration')->nullable(); // Duration in seconds
      $table->date('release_date')->nullable();
      $table->boolean('is_published')->default(true);

      // View counter
      $table->bigInteger('view_count')->default(0);

      $table->timestamps();

      // Ensure unique episode numbers per season
      $table->unique(['season_id', 'episode_number']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('episodes');
  }
};
