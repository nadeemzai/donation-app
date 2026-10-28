<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Locked counters for public document numbers (BR-04), e.g. DN-2026-000001.
 * One row per key per year. Read and incremented only by NumberSequenceService
 * inside a transaction with lockForUpdate() (Week 1, Day 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50); // donation, receipt, disbursement, donor
            $table->string('prefix', 10); // DN, RC, DS, DR (snapshot from NumberingSettings)
            $table->unsignedSmallInteger('year'); // calendar year, Asia/Karachi
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['key', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
