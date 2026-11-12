<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every gateway callback, stored word for word BEFORE it is processed (CLAUDE.md
 * rule 8, FR-PAY-02). Evidence for disputes and reconciliation. Never deleted; only
 * the outcome columns are filled in after processing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 20); // PaymentMethod
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 30)->nullable()->index(); // our reference, if the payload has one
            $table->string('gateway_ref', 100)->nullable()->index();
            $table->string('http_method', 10);
            $table->string('url', 500);
            $table->string('ip', 45)->nullable();
            $table->json('headers')->nullable(); // auth/cookie headers removed
            $table->longText('payload'); // raw request body, as received
            $table->json('fields')->nullable(); // parsed form/JSON fields
            $table->boolean('signature_valid')->nullable();
            $table->string('outcome', 20)->default('received'); // WebhookOutcome
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['method', 'created_at']);
            $table->index('outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
    }
};
