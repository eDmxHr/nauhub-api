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
        Schema::create('upload_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->enum('model_type', ['video', 'episode']);
            $table->unsignedBigInteger('model_id')->nullable();
            $table->enum('upload_method', ['magnet', 'direct', 'tus', 'url']);
            $table->enum('status', ['pending', 'downloading', 'uploading', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('current_phase')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedBigInteger('bytes_transferred')->default(0);
            $table->unsignedBigInteger('bytes_total')->default(0);
            $table->unsignedBigInteger('transfer_id')->nullable();
            $table->string('cloudflare_uid')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('status');
            $table->index('transfer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_jobs');
    }
};
