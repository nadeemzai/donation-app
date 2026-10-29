<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every message we try to send — WhatsApp now, email/SMS from Day 3 (FR-NTF-01..04).
 * One row per message: queued → sent / failed, or skipped (with the reason) when the
 * donor hasn't opted in, the number is invalid or the channel is off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20); // MessageChannel
            $table->string('status', 20)->default('queued'); // MessageStatus
            $table->string('template', 50)->nullable(); // e.g. receipt, thank_you, test
            $table->foreignId('donor_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject'); // the donation / receipt it is about
            $table->string('recipient', 150)->nullable(); // 923001234567@c.us, email…
            $table->text('body')->nullable();
            $table->string('attachment_disk', 30)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_name', 150)->nullable();
            $table->string('provider_message_id', 100)->nullable()->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable(); // or the reason it was skipped
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['channel', 'status']);
            $table->index(['donor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
