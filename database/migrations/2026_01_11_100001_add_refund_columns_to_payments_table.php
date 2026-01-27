<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds refund tracking columns to payments table
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('refund_status', ['none', 'partially_refunded', 'refunded'])
                ->default('none')
                ->after('status');
            $table->decimal('refunded_amount', 10, 2)->nullable()->after('refund_status');
            $table->string('refund_reason')->nullable()->after('refunded_amount');
            $table->timestamp('refunded_at')->nullable()->after('refund_reason');
            $table->foreignId('refunded_by')->nullable()->after('refunded_at')
                ->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['refunded_by']);
            $table->dropColumn([
                'refund_status',
                'refunded_amount',
                'refund_reason',
                'refunded_at',
                'refunded_by',
            ]);
        });
    }
};
