<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per gift (FR-DON-01). Never deleted (BR-05): mistakes are corrected
 * with refunds/reversals. Money counts only once status = paid (BR-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('donation_no', 20)->unique(); // DN-2026-000001 (BR-04)
            $table->foreignId('donor_id')->constrained()->restrictOnDelete();
            $table->foreignId('fund_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->restrictOnDelete();

            $table->decimal('amount', 14, 2);
            $table->decimal('refunded_amount', 14, 2)->default(0);
            $table->char('currency', 3)->default('PKR');

            $table->string('status', 20)->default('pending'); // DonationStatus
            $table->string('channel', 20); // DonationChannel
            $table->string('payment_method', 20); // PaymentMethod
            $table->string('reference', 100)->nullable(); // cheque no., bank / gateway reference

            $table->boolean('is_anonymous')->default(false); // BR-08
            $table->string('dedication', 200)->nullable(); // "In memory of…"
            $table->text('donor_note')->nullable();
            $table->text('staff_note')->nullable();

            $table->timestamp('received_at')->nullable(); // when the money actually arrived (offline)
            $table->timestamp('paid_at')->nullable(); // when it was confirmed
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique(); // blocks double submits

            $table->timestamps();

            $table->index(['status', 'paid_at']);
            $table->index(['fund_id', 'status']);
            $table->index(['campaign_id', 'status']);
            $table->index(['donor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
