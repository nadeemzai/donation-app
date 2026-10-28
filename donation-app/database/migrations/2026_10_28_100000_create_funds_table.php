<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money buckets by type (FR-CMP-01, BR-01, BR-02, BR-10).
 * `balance` is a cached figure maintained only by FundLedgerService (Week 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // ZAKAT, SADQA, …
            $table->string('name', 100);
            $table->string('type', 20); // FundType
            $table->boolean('is_zakat')->default(false); // always derived from type
            $table->text('restriction')->nullable(); // required for restricted funds
            $table->text('description')->nullable();
            $table->decimal('balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
            $table->index('is_zakat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funds');
    }
};
