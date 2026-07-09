<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Booking — ERD fields: booking_id (PK), request_id (FK), tenant_id
     * (FK), user_id (FK), booking_type, booking_date, start_time,
     * end_time, status, created_at. Matches the ERD exactly.
     *
     * NOTE: facility_id was removed to strictly match the ERD (the ERD
     * only puts facility_id on BookingRequest, not on Booking). Any code
     * that previously read booking->facility_id directly will need to go
     * through booking->request->facility_id instead — check
     * SchedulingService and anywhere else that queried Booking by
     * facility_id.
     *
     * booking_type values: 'Instant' | 'Request' — set from which path
     * Algorithm 1 took (approval_tier == 0 vs > 0).
     * status values: 'Confirmed' | 'Checked_In' | 'Cancelled_No_Show'
     * (a Booking row is only ever created once a request is confirmed —
     * 'Pending'/'Rejected' belong to BookingRequest.status, not here)
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id('booking_id');
            $table->foreignId('request_id')->constrained('booking_requests', 'request_id')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants', 'tenant_id')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->string('booking_type'); // 'Instant' | 'Request'
            $table->date('booking_date');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('status')->default('Confirmed'); // Confirmed | Checked_In | Cancelled_No_Show
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'status'], 'bookings_user_status_idx');
        });

        // Now that bookings exists, backfill the other half of the
        // BookingRequest <-> Booking two-way link from the previous
        // migration (couldn't be added there since this table didn't
        // exist yet).
        Schema::table('booking_requests', function (Blueprint $table) {
            $table->foreign('booking_id')->references('booking_id')->on('bookings')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
        });
        Schema::dropIfExists('bookings');
    }
};
