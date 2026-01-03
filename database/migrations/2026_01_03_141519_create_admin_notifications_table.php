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
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['error', 'warning', 'info', 'payment', 'user', 'system'])->default('info');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional context data
            $table->string('action_url')->nullable(); // Optional URL to view/resolve the issue
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->foreignId('related_activity_log_id')->nullable()->constrained('activity_logs')->onDelete('cascade');
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['is_read', 'created_at']);
            $table->index(['type', 'priority']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
