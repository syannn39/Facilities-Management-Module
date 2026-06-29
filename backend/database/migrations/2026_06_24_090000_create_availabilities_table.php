<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Availability — ERD fields: availability_id (PK), facility_id (FK),
     * date, start_time, end_time, is_blocked, created_at.
     *
     * Represents an explicit admin-set block on a facility (e.g. "Gym
     * closed for maintenance on 2026-07-01, 09:00-12:00") — distinct from
     * a slot being unavailable just because someone already booked it.
     * SchedulingService::getAvailability() now checks rows here (where
     * is_blocked = true) in addition to existing Booking rows, so an
     * admin-blocked slot shows up the same way a booked slot does: greyed
     * out, not selectable.
     */
    public function up(): void
    {
        Schema::create('availabilities', function (Blueprint $table) {
            $table->id('availability_id');
            $table->foreignId('facility_id')->constrained('facilities', 'facility_id')->onDelete('cascade');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_blocked')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availabilities');
    }
};
