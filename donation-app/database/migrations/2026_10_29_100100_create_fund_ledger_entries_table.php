<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only fund ledger (FR-FIN-01, NFR-06). Every confirmed donation,
 * refund and paid disbursement adds exactly one row. Rows are never updated
 * or deleted. balance_after = the fund's running balance after this entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 10); // LedgerEntryType: credit | debit
            $table->decimal('amount', 14, 2); // always positive
            $table->decimal('balance_after', 14, 2);
            $table->morphs('source'); // Donation, DonationRefund, Disbursement…
            $table->string('description', 255);
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at');
            $table->timestamp('created_at')->useCurrent();

            // One ledger entry per source record — a donation can never be credited twice.
            $table->unique(['source_type', 'source_id'], 'ledger_one_entry_per_source');
            $table->index(['fund_id', 'posted_at']);
            $table->index(['fund_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_ledger_entries');
    }
};
