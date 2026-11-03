<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pledge periods (Week 8 Day 2): one row per due date, so Finance can see which months a
 * donor paid. Created by the nightly reminder; marked paid when the reminder's donation
 * is confirmed; marked missed when the next period comes round unpaid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_plan_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_plan_id')->constrained('recurring_plans')->cascadeOnDelete();
            $table->date('due_on');
            $table->decimal('amount', 14, 2); // the pledge amount at the time
            $table->string('status', 20)->default('due'); // due | paid | missed
            $table->foreignId('donation_id')->nullable()->constrained('donations')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->unsignedTinyInteger('reminder_count')->default(0);
            $table->timestamps();

            $table->unique(['recurring_plan_id', 'due_on']);
            $table->index(['status', 'due_on']);
        });

        Schema::table('donations', function (Blueprint $table) {
            // The pledge period this donation pays, when it came from a reminder's pay link.
            $table->foreignId('recurring_plan_period_id')->nullable()->after('campaign_id')
                ->constrained('recurring_plan_periods')->nullOnDelete();
        });

        $now = now();

        DB::table('message_templates')->insert([
            'key' => 'pledge_reminder',
            'name' => 'Pledge reminder',
            'description' => 'Sent on a pledge\'s due date (and once more three days later if unpaid), with a link to pay.',
            'whatsapp_body' => "Assalam-o-Alaikum {donor_name},\n\nYour pledge of {amount} to {fund} is due on {due_date}.\n\nYou can give in a minute here:\n{pay_link}\n\nJazakAllah khair.\n— {org_name}",
            'whatsapp_body_ur' => "السلام علیکم {donor_name}،\n\n{fund} کے لیے آپ کا وعدہ کردہ عطیہ {amount} کی تاریخ {due_date} ہے۔\n\nیہاں ایک منٹ میں عطیہ دیں:\n{pay_link}\n\nجزاک اللہ خیر۔\n— {org_name}",
            'email_subject' => 'Your pledge of {amount} is due on {due_date}',
            'email_subject_ur' => null,
            'email_body' => "Dear {donor_name},\n\nThis is a gentle reminder that your pledge of {amount} to {fund} is due on {due_date}.\n\nYou can give here:\n{pay_link}\n\nIf you have already given, thank you — please ignore this message.\n\n{org_name}",
            'email_body_ur' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', 'pledge_reminder')->delete();

        Schema::table('donations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_plan_period_id');
        });

        Schema::dropIfExists('recurring_plan_periods');
    }
};
