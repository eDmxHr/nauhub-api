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
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique(); // Support IPv4 and IPv6
            $table->enum('type', ['manual', 'failed_login', 'bot_spam', 'security_threat'])->default('manual');
            $table->enum('block_type', ['permanent', 'temporary'])->default('permanent');
            $table->string('reason')->nullable(); // Why was this IP blocked?
            $table->text('notes')->nullable(); // Additional admin notes
            $table->foreignId('blocked_by_user_id')->nullable()->constrained('users')->onDelete('set null'); // Admin who blocked it
            $table->timestamp('expires_at')->nullable(); // For temporary blocks
            $table->integer('failed_attempts')->default(0); // Track failed login attempts
            $table->timestamp('last_attempt_at')->nullable(); // Last failed login attempt
            $table->boolean('is_active')->default(true); // Can be deactivated without deleting
            $table->timestamps();

            // Indexes
            $table->index('ip_address');
            $table->index(['ip_address', 'is_active']);
            $table->index('type');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
    }
};
