<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Donors (FR-DNR-01, FR-DNR-02). Separate from staff users; guard `donor`,
 * Sanctum tokens for the mobile API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donors', function (Blueprint $table) {
            $table->id();
            $table->string('donor_code', 20)->nullable()->unique(); // DR-2026-000001, set on Day 5
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20)->nullable()->unique(); // 03XXXXXXXXX
            $table->text('cnic')->nullable(); // encrypted
            $table->string('cnic_hash', 64)->nullable()->unique(); // HMAC for uniqueness / lookup
            $table->text('ntn')->nullable(); // encrypted
            $table->string('type', 20)->default('individual'); // DonorType
            $table->string('city', 100)->nullable();
            $table->string('password')->nullable(); // null = guest donor
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->json('preferences')->nullable(); // channels, opt-ins
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
        });

        Schema::create('donor_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donor_password_reset_tokens');
        Schema::dropIfExists('donors');
    }
};
