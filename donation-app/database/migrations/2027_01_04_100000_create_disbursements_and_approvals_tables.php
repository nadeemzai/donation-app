<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Week 10 Day 1 (FR-DSB-01/02, BR-02/06/07): money going out, and the approval decisions it needs.
 *
 * - disbursements: one payout from one fund, for an approved application or a project.
 *   required_approvals and self_approval_allowed are fixed when the request is made.
 *   project_id gets its foreign key with the projects table (Day 4).
 * - approvals: polymorphic, one row per decision and level; a rejection ends the chain.
 *   Never edited or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('disbursement_no', 30)->unique(); // DS-2027-000001
            $table->foreignId('fund_id')->constrained('funds')->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->restrictOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained('beneficiaries')->restrictOnDelete(); // from the application
            $table->unsignedBigInteger('project_id')->nullable()->index(); // FK added on Day 4
            $table->decimal('amount', 14, 2);
            $table->string('method', 20); // cash | bank | wallet | in_kind
            $table->string('payee_name', 120);
            $table->text('payee_account')->nullable(); // encrypted: IBAN / wallet number
            $table->text('purpose');
            $table->string('status', 20)->default('requested');
            $table->unsignedTinyInteger('required_approvals')->default(1);
            $table->boolean('self_approval_allowed')->default(false);
            $table->boolean('is_self_approved')->default(false);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_reference', 100)->nullable(); // cheque no., bank / wallet transaction id
            $table->timestamps();

            $table->index(['status', 'fund_id']);
            $table->index('is_self_approved');
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->unsignedTinyInteger('level');
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20); // approved | rejected
            $table->text('comment')->nullable();
            $table->boolean('is_self_approval')->default(false);
            $table->timestamp('decided_at');
            $table->timestamps();

            // One decision per level, and one person decides once per request.
            $table->unique(['approvable_type', 'approvable_id', 'level']);
            $table->unique(['approvable_type', 'approvable_id', 'approver_id']);
            $table->index(['approver_id', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('disbursements');
    }
};
