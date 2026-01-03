<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'ready', 'error'])
                ->default('pending')
                ->after('cloudflare_video_uid');
        });

        Schema::table('episodes', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'ready', 'error'])
                ->default('pending')
                ->after('cloudflare_video_uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
