<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Creates playlists table - user-created lists like "My List", "Favorites", "Watch Later"
   */
  public function up(): void
  {
    Schema::create('playlists', function (Blueprint $table) {
      $table->id();

      // Owner
      $table->foreignId('user_id')->constrained()->onDelete('cascade');
      $table->foreignId('profile_id')->nullable()->constrained()->onDelete('cascade');

      // Playlist details
      $table->string('name'); // e.g., "My List", "Favorites", "Watch Later"
      $table->text('description')->nullable();

      // System playlists vs user-created
      $table->boolean('is_system')->default(false); // true for default "My List" playlist

      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('playlists');
  }
};
