<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Renames 'rating' column to 'age_rating' for clarity
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->renameColumn('rating', 'age_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->renameColumn('age_rating', 'rating');
        });
    }
};
