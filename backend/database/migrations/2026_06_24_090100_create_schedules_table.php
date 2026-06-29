<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schedule — ERD fields: schedule_id (PK), facility_id (FK), tenant_id
     * (FK), booking_id (FK), slot_date, start_time, end_time, is_available.
     *
     * One row per slot-actually-taken-by-a-booking (booking_id NOT NULL —
     * Schedule records occupancy that already happened, it doesn't predict
     * future open slots; that's still computed on the fly in
     * SchedulingService::getAvailability() from Booking + Availability,
     * since pre-generating every open slot for every facility for every
     * future date would mean inserting rows nobody may ever book).
     * Written by SchedulingService::validateAndCreateBooking() right after
     * a Booking is created, so it stays a true historical record of what
     * was actually reserved and when.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id('schedule_id');
            $table->foreignId('facility_id')->constrained('facilities', 'facility_id')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants', 'tenant_id')->onDelete('cascade');
            $table->foreignId('booking_id')->constrained('bookings', 'booking_id')->onDelete('cascade');
            $table->date('slot_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(false); // false: this slot is taken by the linked booking
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
