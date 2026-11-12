<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries that grow with the data (Week 12 Day 2 performance review).
 * Each one matches a real query; see docs/performance-report.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Settlement matching: "succeeded on this gateway on this day, not yet settled".
            $table->index(['method', 'status', 'completed_at'], 'payments_method_status_completed_index');
        });

        Schema::table('disbursements', function (Blueprint $table) {
            // Fund report and project spend: paid payouts of a fund within a period.
            $table->index(['fund_id', 'status', 'paid_at'], 'disbursements_fund_status_paid_index');
        });

        Schema::table('qurbani_bookings', function (Blueprint $table) {
            // qurbani:expire every 15 minutes: pending holds that ran out.
            $table->index(['status', 'hold_until'], 'qurbani_bookings_status_hold_index');
        });

        Schema::table('activity_log', function (Blueprint $table) {
            // The audit log grows fastest of all; its screen lists the newest first.
            $table->index('created_at', 'activity_log_created_at_index');
            $table->index(['event', 'created_at'], 'activity_log_event_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_event_created_index');
            $table->dropIndex('activity_log_created_at_index');
        });

        Schema::table('qurbani_bookings', function (Blueprint $table) {
            $table->dropIndex('qurbani_bookings_status_hold_index');
        });

        Schema::table('disbursements', function (Blueprint $table) {
            $table->dropIndex('disbursements_fund_status_paid_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_method_status_completed_index');
        });
    }
};
