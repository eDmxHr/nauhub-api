<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates support_tickets table for customer support requests
     */
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();

            // Ticket reference
            $table->string('ticket_number')->unique(); // e.g., TKT-2026-00001

            // User who created the ticket
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Ticket type/category
            $table->enum('category', [
                'movie_request',      // Request for specific movie
                'series_request',     // Request for specific series
                'technical_issue',    // App/streaming issues
                'billing',            // Payment/subscription issues
                'account',            // Account related issues
                'feedback',           // General feedback
                'other'               // Other requests
            ]);

            // Priority
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            // Ticket details
            $table->string('subject');
            $table->text('description');
            $table->json('attachments')->nullable(); // Array of attachment URLs

            // Status tracking
            $table->enum('status', [
                'open',           // New ticket
                'in_progress',    // Being worked on
                'awaiting_reply', // Waiting for customer response
                'resolved',       // Issue resolved
                'closed'          // Ticket closed
            ])->default('open');

            // Admin assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable();

            // Resolution details
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');

            // Timestamps
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            // Indexes for faster queries
            $table->index(['user_id', 'status']);
            $table->index(['category', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        // Create ticket replies table for conversation thread
        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('message');
            $table->json('attachments')->nullable();
            $table->boolean('is_admin_reply')->default(false);
            $table->boolean('is_internal_note')->default(false); // Internal notes not visible to customer
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
    }
};
