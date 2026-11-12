<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Week 11 Day 3 (FR-INV-01/02): goods the NGO holds and gives out.
 *
 * - inventory_items: what is kept (rice, blankets, school bags…) with an estimated value per
 *   unit. quantity_on_hand is changed only by InventoryService, together with a movement line.
 * - in_kind_donations (+ lines): goods received from donors, with an estimated value.
 * - stock_movements: every stock in / out / count correction, append-only, with the running
 *   quantity after it (like the fund ledger). Stock never goes below zero.
 * - disbursement_items: the goods an in-kind disbursement hands over (stock out when paid).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('category', 20);
            $table->string('unit', 20);
            $table->decimal('unit_value', 12, 2)->default(0); // estimated value of one unit
            $table->decimal('quantity_on_hand', 12, 2)->default(0);
            $table->decimal('reorder_level', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('in_kind_donations', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 30)->unique(); // GR-2027-000001
            $table->foreignId('donor_id')->nullable()->constrained('donors')->nullOnDelete();
            $table->string('donor_name', 120)->nullable(); // walk-in giver without a donor record
            $table->timestamp('received_at');
            $table->decimal('total_value', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('in_kind_donation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('in_kind_donation_id')->constrained('in_kind_donations')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_value', 12, 2);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->string('direction', 10); // in | out | adjust
            $table->decimal('quantity', 12, 2); // signed: + in, − out
            $table->decimal('unit_value', 12, 2);
            $table->decimal('quantity_after', 12, 2);
            $table->nullableMorphs('source'); // in-kind donation, disbursement or none (count)
            $table->string('note', 255)->nullable();
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['item_id', 'id']);
        });

        Schema::create('disbursement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disbursement_id')->constrained('disbursements')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_value', 12, 2);
            $table->timestamps();

            $table->unique(['disbursement_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursement_items');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('in_kind_donation_lines');
        Schema::dropIfExists('in_kind_donations');
        Schema::dropIfExists('inventory_items');
    }
};
