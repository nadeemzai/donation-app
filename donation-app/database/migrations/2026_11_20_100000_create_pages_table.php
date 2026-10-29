<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Website pages edited in the admin (Week 6 Day 5): About us, Contact, Privacy…
 * Served at /{slug}. About and Contact are created here so the menu works on day one;
 * the founder rewrites their text in Admin → Website → Pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('title', 120);
            $table->string('meta_description', 255)->nullable();
            $table->longText('body')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('show_contact_form')->default(false);
            $table->boolean('show_in_footer')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('pages')->insert([
            [
                'slug' => 'about',
                'title' => 'About us',
                'meta_description' => 'Who we are, what we do and how we look after every rupee you give.',
                'body' => '<h2>Who we are</h2><p>We are a registered welfare organisation in Pakistan. We collect Zakat, Sadqa and donations and deliver them to families in need.</p><h2>How we look after your money</h2><ul><li>Every donation gets a receipt.</li><li>Zakat is kept in its own fund and spent only on people who are eligible.</li><li>Every fund has its own ledger, checked by our finance team.</li></ul><p><em>Please replace this text with your own story in Admin → Website → Pages.</em></p>',
                'is_published' => true,
                'show_contact_form' => false,
                'show_in_footer' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact us',
                'meta_description' => 'Questions about a donation, a receipt or our work? Send us a message.',
                'body' => '<p>Questions about a donation, a receipt or our work? Send us a message and we will reply within one working day. Please include your donation number if you have one.</p>',
                'is_published' => true,
                'show_contact_form' => true,
                'show_in_footer' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy policy',
                'meta_description' => 'What we collect about donors, why, and how we protect it.',
                'body' => '<h2>What we collect</h2><p>Your name, mobile number, email, city and — only if you give it — your CNIC for tax certificates.</p><h2>Why</h2><p>To record your donation, send your receipt and tax certificate, and tell you how your money was used.</p><h2>How we protect it</h2><ul><li>Your CNIC is stored encrypted and never shown in full.</li><li>We never sell or share your details.</li><li>We send WhatsApp messages only if you agree, and you can switch them off in My donations.</li></ul><p><em>Draft — please review with your adviser before publishing.</em></p>',
                'is_published' => false,
                'show_contact_form' => false,
                'show_in_footer' => true,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
