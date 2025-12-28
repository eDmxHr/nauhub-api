<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Creates seasons table - for TV shows and anime series
   */
  public function up(): void
  {
    Schema::create('seasons', function (Blueprint $table) {
      $table->id();

      // Foreign key to videos table (parent series/anime)
      $table->foreignId('video_id')->constrained()->onDelete('cascade');

      // Season details
      $table->integer('season_number'); // Season 1, 2, 3, etc.
      $table->string('title')->nullable(); // Optional title (e.g., "The Beginning", "Final Season")
      $table->text('description')->nullable();
      $table->string('thumbnail')->nullable(); // Season-specific cover image

      // Metadata
      $table->integer('release_year')->nullable();
      $table->boolean('is_published')->default(true);

      $table->timestamps();

      // Ensure unique season numbers per video
      $table->unique(['video_id', 'season_number']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('seasons');
  }
};
