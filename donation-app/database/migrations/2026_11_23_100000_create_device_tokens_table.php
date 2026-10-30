<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push notification tokens from the mobile app (Week 7 Day 4). One row per device token;
 * a token that moves to another donor (shared phone) moves with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_id')->constrained('donors')->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->string('platform', 10); // android | ios
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('donor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
