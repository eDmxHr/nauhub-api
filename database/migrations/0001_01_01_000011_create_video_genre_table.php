<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Creates pivot table for many-to-many relationship between videos and genres
   */
  public function up(): void
  {
    Schema::create('video_genre', function (Blueprint $table) {
      $table->id();

      // Foreign keys
      $table->foreignId('video_id')->constrained()->onDelete('cascade');
      $table->foreignId('genre_id')->constrained()->onDelete('cascade');

      $table->timestamps();

      // Prevent duplicate entries
      $table->unique(['video_id', 'genre_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('video_genre');
  }
};
