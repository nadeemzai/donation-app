<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Week 11 Day 2 (FR-QRB-03): the three Qurbani updates donors get — animal allocated (with the
 * tag and day), Qurbani done, meat distributed. Editable in Message templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = [
            [
                'key' => 'qurbani_allocated',
                'name' => 'Qurbani — animal allocated',
                'description' => 'Sent once a paid booking has its animal(s) and day.',
                'whatsapp_body' => "Assalam-o-Alaikum {donor_name},\n\nYour Qurbani booking {booking_no} is ready: {animal}, tag {tags}, on {qurbani_day}.\n\nOn behalf of: {share_names}\n\nWe will message you when the Qurbani is done, In sha Allah.\n— {org_name}",
                'whatsapp_body_ur' => "السلام علیکم {donor_name}،\n\nآپ کی قربانی بکنگ {booking_no} تیار ہے: {animal}، ٹیگ {tags}، {qurbani_day}۔\n\nجن کی طرف سے: {share_names}\n\nقربانی ہوتے ہی ہم آپ کو اطلاع دیں گے، ان شاء اللہ۔\n— {org_name}",
                'email_subject' => 'Your Qurbani {booking_no}: {animal} {tags}, {qurbani_day}',
                'email_body' => "Dear {donor_name},\n\nYour Qurbani booking {booking_no} is ready.\n\nAnimal: {animal}, tag {tags}\nDay: {qurbani_day}\nOn behalf of: {share_names}\n\nWe will email you again when the Qurbani is done, In sha Allah.\n\n{org_name}",
            ],
            [
                'key' => 'qurbani_done',
                'name' => 'Qurbani — done',
                'description' => 'Sent when every animal of a booking has been slaughtered.',
                'whatsapp_body' => "Assalam-o-Alaikum {donor_name},\n\nAlhamdulillah, your Qurbani ({booking_no}, {animal} {tags}) was offered today on behalf of {share_names}.\n\nMay Allah accept it. The meat is being distributed to families in need.\n— {org_name}",
                'whatsapp_body_ur' => "السلام علیکم {donor_name}،\n\nالحمدللہ، آپ کی قربانی ({booking_no}، {animal} {tags}) آج {share_names} کی طرف سے ادا کر دی گئی۔\n\nاللہ تعالیٰ قبول فرمائے۔ گوشت ضرورت مند خاندانوں میں تقسیم کیا جا رہا ہے۔\n— {org_name}",
                'email_subject' => 'Alhamdulillah — your Qurbani {booking_no} is done',
                'email_body' => "Dear {donor_name},\n\nAlhamdulillah, your Qurbani ({booking_no}, {animal} {tags}) has been offered on behalf of {share_names}.\n\nMay Allah accept it. The meat is now being distributed to families in need.\n\n{org_name}",
            ],
            [
                'key' => 'qurbani_distributed',
                'name' => 'Qurbani — meat distributed',
                'description' => 'Sent when the meat of every animal of a booking has been distributed.',
                'whatsapp_body' => "Assalam-o-Alaikum {donor_name},\n\nThe meat from your Qurbani ({booking_no}) has reached families in need. JazakAllah khair for your trust.\n— {org_name}",
                'whatsapp_body_ur' => "السلام علیکم {donor_name}،\n\nآپ کی قربانی ({booking_no}) کا گوشت ضرورت مند خاندانوں تک پہنچ گیا ہے۔ آپ کے اعتماد کا جزاک اللہ خیر۔\n— {org_name}",
                'email_subject' => 'The meat from your Qurbani {booking_no} has been distributed',
                'email_body' => "Dear {donor_name},\n\nThe meat from your Qurbani ({booking_no}) has reached families in need.\n\nJazakAllah khair for your trust.\n\n{org_name}",
            ],
        ];

        foreach ($rows as $row) {
            DB::table('message_templates')->insert([...$row, 'email_subject_ur' => null, 'email_body_ur' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('message_templates')->whereIn('key', ['qurbani_allocated', 'qurbani_done', 'qurbani_distributed'])->delete();
    }
};
