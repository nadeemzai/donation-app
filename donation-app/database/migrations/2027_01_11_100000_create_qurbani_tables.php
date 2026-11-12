<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Week 11 Day 1 (FR-QRB-01/02): Qurbani seasons, what is offered (animal + price per share),
 * the tagged animals (allocated on Day 2), bookings and the names each share is offered for.
 *
 * - Cow / camel = 7 shares, goat / sheep = 1 share.
 * - Slaughter on the 3 days of Eid ul Adha; booking closes 3 days before Eid.
 * - A booking is paid through the normal donation flow (donations.qurbani_booking_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qurbani_seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120); // e.g. "Eid ul Adha 1448"
            $table->date('eid_date'); // first day of Eid
            $table->date('booking_closes_on'); // eid_date − 3 days (set by the model)
            $table->foreignId('fund_id')->constrained('funds')->restrictOnDelete();
            $table->string('status', 20)->default('draft'); // draft | open | closed
            $table->boolean('allow_overseas')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'eid_date']);
        });

        Schema::create('qurbani_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('qurbani_seasons')->cascadeOnDelete();
            $table->string('animal', 20); // cow | camel | goat | sheep
            $table->unsignedTinyInteger('shares_per_animal');
            $table->decimal('price_per_share', 12, 2);
            $table->unsignedInteger('max_shares')->nullable(); // null = no limit
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['season_id', 'animal']);
        });

        Schema::create('qurbani_animals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('qurbani_seasons')->cascadeOnDelete();
            $table->foreignId('offering_id')->constrained('qurbani_offerings')->restrictOnDelete();
            $table->string('tag_no', 30);
            $table->string('status', 20)->default('available'); // available | allocated | slaughtered | distributed
            $table->unsignedTinyInteger('slaughter_day')->nullable(); // 1–3 of Eid
            $table->string('location', 150)->nullable();
            $table->timestamp('slaughtered_at')->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'tag_no']);
        });

        Schema::create('qurbani_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_no', 30)->unique(); // QB-2027-000001
            $table->foreignId('season_id')->constrained('qurbani_seasons')->restrictOnDelete();
            $table->foreignId('offering_id')->constrained('qurbani_offerings')->restrictOnDelete();
            $table->foreignId('donor_id')->constrained('donors')->restrictOnDelete();
            $table->unsignedSmallInteger('shares');
            $table->decimal('price_per_share', 12, 2); // as booked
            $table->decimal('total_amount', 14, 2);
            $table->string('status', 20)->default('pending'); // pending | confirmed | cancelled
            $table->string('channel', 20); // web | mobile | admin
            $table->timestamp('hold_until')->nullable(); // shares held while the donor pays
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['season_id', 'offering_id', 'status']);
        });

        Schema::create('qurbani_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('qurbani_bookings')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name', 120); // on whose behalf the Qurbani is offered
            $table->foreignId('animal_id')->nullable()->constrained('qurbani_animals')->nullOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'position']);
            $table->index('animal_id');
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->foreignId('qurbani_booking_id')->nullable()->after('recurring_plan_period_id')
                ->constrained('qurbani_bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qurbani_booking_id');
        });

        Schema::dropIfExists('qurbani_shares');
        Schema::dropIfExists('qurbani_bookings');
        Schema::dropIfExists('qurbani_animals');
        Schema::dropIfExists('qurbani_offerings');
        Schema::dropIfExists('qurbani_seasons');
    }
};
