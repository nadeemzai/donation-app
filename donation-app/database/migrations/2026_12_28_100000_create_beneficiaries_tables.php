<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programs (Week 9, FR-BEN-01..03): beneficiaries, their applications for help, and the
 * field officer's verification visits. CNIC is stored encrypted with an HMAC hash for
 * uniqueness and lookup (same scheme as donors). Beneficiaries are archived, never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // BN-2027-000001
            $table->string('name', 120);
            $table->string('guardian_name', 120)->nullable(); // father / husband
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('cnic')->nullable(); // encrypted
            $table->string('cnic_hash', 64)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('area', 120)->nullable(); // mohalla / village / union council
            $table->unsignedSmallInteger('household_size')->default(1);
            $table->unsignedSmallInteger('dependents')->default(0);
            $table->decimal('monthly_income', 12, 2)->default(0);
            $table->json('vulnerabilities')->nullable();
            $table->boolean('is_zakat_eligible')->default(false);
            $table->timestamp('zakat_decided_at')->nullable();
            $table->foreignId('zakat_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('registered');
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'city']);
            $table->index('assigned_officer_id');
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no', 30)->unique(); // AP-2027-000001
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->restrictOnDelete();
            $table->string('category', 20);
            $table->text('description');
            $table->decimal('requested_amount', 14, 2)->nullable();
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete(); // suggested fund
            $table->string('status', 20)->default('submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index('assigned_officer_id');
        });

        Schema::create('verification_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('visit_date');
            $table->text('findings');
            $table->unsignedSmallInteger('household_size_seen')->nullable();
            $table->decimal('monthly_income_found', 12, 2)->nullable();
            $table->string('recommendation', 20);
            $table->boolean('zakat_eligible')->nullable(); // officer's view; the decision is on the beneficiary
            $table->text('zakat_reason')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_visits');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('beneficiaries');
    }
};
