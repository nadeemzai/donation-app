<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Week 10 Day 4 (FR-PRJ-01): projects with a budget, a fund, milestones and a status.
 * Spend is not stored — it is the sum of the project's paid disbursements. Projects are
 * archived, never deleted. Also gives disbursements.project_id its foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // PR-2027-000001
            $table->string('name', 150);
            $table->string('slug', 170)->unique(); // public page (Day 5)
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('fund_id')->constrained('funds')->restrictOnDelete();
            $table->decimal('budget', 14, 2);
            $table->string('status', 20)->default('planned');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('location', 150)->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_public')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'fund_id']);
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
        });

        Schema::table('disbursements', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('disbursements', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::dropIfExists('milestones');
        Schema::dropIfExists('projects');
    }
};
