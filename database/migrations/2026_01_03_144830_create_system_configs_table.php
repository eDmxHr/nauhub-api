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
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g., 'site.name', 'payment.method', 'cloudflare.enabled'
            $table->text('value')->nullable(); // Stored as JSON for complex values
            $table->string('type')->default('string'); // string, integer, boolean, json, array
            $table->text('description')->nullable(); // What this config does
            $table->string('group')->default('general'); // general, payment, api, features, etc.
            $table->boolean('is_public')->default(false); // Can non-admins see this?
            $table->boolean('is_editable')->default(true); // Can this be edited via UI?
            $table->timestamps();

            // Indexes
            $table->index('key');
            $table->index(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_configs');
    }
};
