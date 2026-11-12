<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Donation receipts (FR-RCP-01, BR-04). One receipt per paid donation, numbered
 * RC-YYYY-000001 from the number sequence. "snapshot" freezes what the receipt says at
 * the moment it was issued (organisation, donor, amount, fund), so reprinting it later
 * always gives the same document. Never deleted; a refunded donation's receipt is voided.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 30)->unique();
            $table->foreignId('donation_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('PKR');
            $table->json('snapshot');
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path', 255)->nullable(); // null until the PDF is rendered
            $table->string('file_hash', 64)->nullable(); // sha256 of the PDF
            $table->timestamp('rendered_at')->nullable();
            $table->unsignedSmallInteger('render_count')->default(0);
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->index('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
