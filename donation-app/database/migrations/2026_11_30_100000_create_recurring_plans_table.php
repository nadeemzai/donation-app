<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pledges — recurring donations (Week 8 Day 1, FR-DON-03). A pledge is a promise, not money:
 * nothing reaches a fund until the donor pays the reminder's donation. Reminders and
 * fulfilment tracking arrive on Day 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_id')->constrained('donors');
            $table->foreignId('fund_id')->constrained('funds');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns');
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('PKR');
            $table->string('frequency', 20); // monthly | quarterly | yearly
            $table->unsignedTinyInteger('day_of_month'); // 1–28, so every month has it
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_due_on')->nullable(); // null once cancelled / completed
            $table->string('status', 20)->default('active');
            $table->string('payment_method', 30)->nullable(); // the donor's usual way to pay
            $table->string('channel', 20); // where it was set up: admin | web | mobile
            $table->text('notes')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'next_due_on']);
            $table->index('donor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_plans');
    }
};
