<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gateway settlement files (Week 11 Day 4, FR-FIN-02): one row per imported CSV and one per line
 * in it, each matched to our payment or flagged. Never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20); // PaymentMethod (jazzcash | easypaisa)
            $table->date('settlement_date');
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->string('file_hash', 64)->unique(); // the same file is never imported twice
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('problem_count')->default(0); // flagged and not yet resolved
            $table->unsignedInteger('ignored_count')->default(0);
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('fee_total', 14, 2)->default(0);
            $table->decimal('net_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at');
            $table->timestamp('last_matched_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'settlement_date']);
        });

        Schema::create('settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_no'); // row number in the file
            $table->string('our_reference', 100)->nullable()->index();
            $table->string('gateway_ref', 100)->nullable()->index();
            $table->decimal('amount', 14, 2)->nullable(); // gross, what the donor paid
            $table->decimal('fee', 14, 2)->nullable();
            $table->decimal('net', 14, 2)->nullable();
            $table->timestamp('transacted_at')->nullable();
            $table->string('gateway_status', 50)->nullable();
            $table->json('raw');
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('match_status', 20)->index(); // SettlementMatch
            $table->string('mismatch_reason', 255)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'match_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_lines');
        Schema::dropIfExists('settlements');
    }
};
