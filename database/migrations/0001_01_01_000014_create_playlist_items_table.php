<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Creates playlist_items table - videos added to playlists
   */
  public function up(): void
  {
    Schema::create('playlist_items', function (Blueprint $table) {
      $table->id();

      // Foreign keys
      $table->foreignId('playlist_id')->constrained()->onDelete('cascade');
      $table->foreignId('video_id')->constrained()->onDelete('cascade');

      // Order in playlist (for custom sorting)
      $table->integer('sort_order')->default(0);

      $table->timestamps();

      // Prevent duplicate videos in same playlist
      $table->unique(['playlist_id', 'video_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('playlist_items');
  }
};
