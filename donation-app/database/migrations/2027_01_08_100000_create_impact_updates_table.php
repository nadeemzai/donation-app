<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Week 10 Day 5 (FR-PRJ-02): progress posts on a project, published to the website and the
 * app, and sent once to the donors who recently gave to the project's fund.
 * published_at null = draft, future = scheduled. notified_at = donors were messaged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->boolean('notify_donors')->default(true);
            $table->timestamp('notified_at')->nullable();
            $table->unsignedInteger('notified_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'published_at']);
            $table->index(['published_at', 'notified_at']);
        });

        $now = now();

        DB::table('message_templates')->insert([
            'key' => 'impact_update',
            'name' => 'Project impact update',
            'description' => 'Sent once when a project update is published, to donors who gave to the project\'s fund in the last 12 months.',
            'whatsapp_body' => "Assalam-o-Alaikum {donor_name},\n\nYour support to {fund} is at work. News from {project}:\n\n*{update_title}*\n\nRead more and see photos:\n{link}\n\nJazakAllah khair.\n— {org_name}",
            'whatsapp_body_ur' => "السلام علیکم {donor_name}،\n\n{fund} کے لیے آپ کا تعاون کام آ رہا ہے۔ {project} کی تازہ خبر:\n\n*{update_title}*\n\nمزید پڑھیں اور تصاویر دیکھیں:\n{link}\n\nجزاک اللہ خیر۔\n— {org_name}",
            'email_subject' => '{project}: {update_title}',
            'email_subject_ur' => null,
            'email_body' => "Dear {donor_name},\n\nThank you for supporting {fund}. Here is news from {project}:\n\n{update_title}\n\nRead the full update and see photos:\n{link}\n\nWith gratitude,\n{org_name}",
            'email_body_ur' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', 'impact_update')->delete();
        Schema::dropIfExists('impact_updates');
    }
};
