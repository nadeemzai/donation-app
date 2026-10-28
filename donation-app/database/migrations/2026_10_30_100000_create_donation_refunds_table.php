<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money given back to a donor (FR-DON-02, BR-05). A refund never edits the
 * donation's amount: it is its own record, posts one ledger debit, and moves the
 * donation to partially_refunded / refunded. Rows are never updated or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_id')->constrained()->restrictOnDelete();
            $table->foreignId('fund_id')->constrained()->restrictOnDelete(); // fund debited
            $table->decimal('amount', 14, 2);
            $table->string('method', 20); // PaymentMethod: how the money went back
            $table->string('reference', 100)->nullable(); // cheque no., bank/wallet transaction id
            $table->text('reason');
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at');
            $table->timestamps();

            $table->index(['donation_id', 'id']);
            $table->index(['fund_id', 'refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_refunds');
    }
};
