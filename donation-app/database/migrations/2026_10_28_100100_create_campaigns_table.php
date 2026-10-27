<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appeals that collect money into exactly one fund (FR-CMP-02, BR-01, BR-09).
 * raised_amount / donor_count are cached totals updated when a donation is
 * confirmed (Week 3) — never edited by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained()->restrictOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('title', 150);
            $table->string('summary', 300)->nullable();
            $table->longText('story')->nullable();
            $table->decimal('goal_amount', 14, 2)->nullable(); // null = open-ended cause
            $table->decimal('raised_amount', 14, 2)->default(0);
            $table->unsignedInteger('donor_count')->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable(); // null = no end date
            $table->string('status', 20)->default('draft'); // CampaignStatus
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'ends_at']);
            $table->index(['status', 'is_featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
