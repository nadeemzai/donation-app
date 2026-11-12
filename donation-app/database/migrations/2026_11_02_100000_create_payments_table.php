<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gateway payment attempts (FR-PAY-01/02). A donation can have several attempts
 * (failed, then retried). Rows are never deleted.
 *
 * - reference: OUR transaction id, sent to the gateway (≤ 20 letters/digits for JazzCash).
 * - gateway_ref: THEIR transaction id. Unique, so the same gateway payment can never
 *   be applied twice (CLAUDE.md rule 8). NULL until the gateway tells us.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_id')->constrained()->restrictOnDelete();
            $table->string('method', 20); // PaymentMethod (gateway methods only)
            $table->string('status', 20)->default('initiated'); // PaymentStatus
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('PKR');
            $table->string('reference', 30)->unique();
            $table->string('gateway_ref', 100)->nullable()->unique();
            $table->string('mode', 10); // sandbox | live, at the time of payment
            $table->string('response_code', 20)->nullable();
            $table->string('response_message', 255)->nullable();
            $table->string('review_reason', 255)->nullable();
            $table->timestamp('initiated_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedSmallInteger('check_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['donation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
