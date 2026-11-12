<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Annual tax certificates (FR-RCP-03, BR-12): one valid certificate per donor per fiscal
 * year (July–June). Reissuing voids the old one and creates a new number — never edited,
 * never deleted. "snapshot" freezes the donor, organisation and donation lines.
 * Also adds the message template used to send them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_no', 30)->unique();
            $table->foreignId('donor_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('fiscal_year'); // year it starts in: 2025 = FY 2025-26
            $table->decimal('total', 14, 2);
            $table->decimal('zakat_total', 14, 2)->default(0);
            $table->unsignedInteger('donation_count');
            $table->string('lines_hash', 64); // what it covers — unchanged lines = no reissue needed
            $table->json('snapshot');
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path', 255)->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->timestamp('rendered_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['fiscal_year', 'voided_at']);
            $table->index(['donor_id', 'fiscal_year']);
        });

        $now = now();

        DB::table('message_templates')->insert([
            'key' => 'tax_certificate',
            'name' => 'Annual tax certificate',
            'description' => 'Sent by Finance with the donor\'s yearly tax certificate PDF.',
            'whatsapp_body' => "Assalam-o-Alaikum {donor_name},\n\nYour tax certificate {certificate_no} for fiscal year {fiscal_year} is attached. Total donations: {amount}.\n\nJazakAllah khair for your support.\n— {org_name}",
            'whatsapp_body_ur' => "السلام علیکم {donor_name}،\n\nمالی سال {fiscal_year} کے لیے آپ کا ٹیکس سرٹیفکیٹ {certificate_no} ساتھ منسلک ہے۔ کل عطیات: {amount}۔\n\nآپ کے تعاون کا بہت شکریہ۔\n— {org_name}",
            'email_subject' => 'Your tax certificate for FY {fiscal_year} — {certificate_no}',
            'email_subject_ur' => null,
            'email_body' => "Dear {donor_name},\n\nPlease find attached your donation tax certificate {certificate_no} for fiscal year {fiscal_year} (1 July – 30 June).\n\nTotal donations in the year: {amount}.\n\nThank you for your generous support.\n\n{org_name}",
            'email_body_ur' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('message_templates')->where('key', 'tax_certificate')->delete();
        Schema::dropIfExists('tax_certificates');
    }
};
