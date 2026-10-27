<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progress posts on a campaign, shown on web/API (FR-CMP-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->longText('body');
            $table->timestamp('published_at')->nullable(); // null = draft
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campaign_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_updates');
    }
};
