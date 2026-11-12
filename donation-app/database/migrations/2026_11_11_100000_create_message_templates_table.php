<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Message wording staff can edit without code changes (FR-NTF-02). One row per message
 * type (fixed keys used by the code); WhatsApp text + email subject/body, each with an
 * optional Urdu version. The starter wording is inserted here so every install has it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->text('whatsapp_body');
            $table->text('whatsapp_body_ur')->nullable();
            $table->string('email_subject', 200);
            $table->string('email_subject_ur', 200)->nullable();
            $table->text('email_body');
            $table->text('email_body_ur')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('message_templates')->insert([
            [
                'key' => 'thank_you_receipt',
                'name' => 'Thank you + receipt',
                'description' => 'Sent when a donation is confirmed, with the PDF receipt attached.',
                'whatsapp_body' => "Assalam-o-Alaikum {donor_name},\n\nJazakAllah khair for your donation of {amount} to {fund}. Your receipt {receipt_no} is attached.\n\nDonation no. {donation_no}\n— {org_name}",
                'whatsapp_body_ur' => "السلام علیکم {donor_name}،\n\n{fund} کے لیے آپ کے {amount} کے عطیہ کا بہت شکریہ۔ جزاک اللہ خیراً۔ آپ کی رسید {receipt_no} ساتھ منسلک ہے۔\n\nعطیہ نمبر {donation_no}\n— {org_name}",
                'email_subject' => 'Thank you for your donation — receipt {receipt_no}',
                'email_subject_ur' => 'آپ کے عطیہ کا شکریہ — رسید {receipt_no}',
                'email_body' => "Dear {donor_name},\n\nThank you for your donation of {amount} to {fund}. Your receipt {receipt_no} is attached to this email.\n\nDonation number: {donation_no}\nDate: {date}\n\nMay Allah accept it from you.\n\n{org_name}",
                'email_body_ur' => "محترم {donor_name}،\n\n{fund} کے لیے آپ کے {amount} کے عطیہ کا بہت شکریہ۔ آپ کی رسید {receipt_no} اس ای میل کے ساتھ منسلک ہے۔\n\nعطیہ نمبر: {donation_no}\nتاریخ: {date}\n\nاللہ تعالیٰ آپ کا عطیہ قبول فرمائے۔\n\n{org_name}",
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'payment_failed',
                'name' => 'Payment did not go through',
                'description' => 'Sent when a JazzCash / Easypaisa payment fails or expires.',
                'whatsapp_body' => "Assalam-o-Alaikum {donor_name}, your payment of {amount} for donation {donation_no} did not go through, and no money was taken. You can try again any time.\n— {org_name}",
                'whatsapp_body_ur' => "السلام علیکم {donor_name}، عطیہ نمبر {donation_no} کے لیے {amount} کی ادائیگی مکمل نہیں ہو سکی اور کوئی رقم نہیں کٹی۔ آپ کسی بھی وقت دوبارہ کوشش کر سکتے ہیں۔\n— {org_name}",
                'email_subject' => 'Your donation payment did not go through',
                'email_subject_ur' => null,
                'email_body' => "Dear {donor_name},\n\nYour payment of {amount} for donation {donation_no} did not go through, and no money was taken from your account.\n\nYou can try again any time on our website or app.\n\n{org_name}",
                'email_body_ur' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'proof_rejected',
                'name' => 'Bank transfer proof rejected',
                'description' => 'Sent when Finance rejects the proof of a bank transfer / Raast donation.',
                'whatsapp_body' => "Assalam-o-Alaikum {donor_name}, we could not confirm your bank transfer of {amount} for donation {donation_no}. Reason: {reason}\n\nPlease contact us, or donate again with the correct proof.\n— {org_name}",
                'whatsapp_body_ur' => null,
                'email_subject' => 'We could not confirm your bank transfer',
                'email_subject_ur' => null,
                'email_body' => "Dear {donor_name},\n\nWe could not confirm your bank transfer of {amount} for donation {donation_no}.\n\nReason: {reason}\n\nPlease reply to this email or donate again with the correct proof.\n\n{org_name}",
                'email_body_ur' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
