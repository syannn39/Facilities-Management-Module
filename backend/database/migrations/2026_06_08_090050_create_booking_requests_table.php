<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BookingRequest — ERD fields: request_id (PK), booking_id (FK),
     * tenant_id (FK), facility_id (FK), user_id (FK), booking_date,
     * start_time, end_time, status, created_at.
     *
     * This is the entry point for every booking attempt (instant or
     * approval-required) — Algorithm 1 always creates a BookingRequest
     * first. booking_id stays NULL until/unless the request is approved
     * (or auto-confirmed for instant bookings), at which point a Booking
     * row is created and this column is backfilled to point at it — see
     * Booking migration below for the other half of this two-way link.
     *
     * status values: 'Pending' | 'Approved' | 'Rejected'
     * (separate from Booking.status, which tracks the post-approval
     * lifecycle: Confirmed → Checked_In / Cancelled_No_Show)
     *
     * NOTE: purpose_of_use and guest_count were removed to strictly match
     * the ERD. Those were backing FR3's "Extended Detail Form" — if that
     * form is still needed, add the fields back to the ERD, or store them
     * elsewhere (e.g. a separate table not covered by this diagram).
     */
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id('request_id');
            // Nullable + added via a separate statement below: at insert
            // time the Booking this request will eventually produce
            // doesn't exist yet, so this FK can't be NOT NULL from the start.
            $table->foreignId('booking_id')->nullable();
            $table->foreignId('tenant_id')->constrained('tenants', 'tenant_id')->onDelete('cascade');
            $table->foreignId('facility_id')->constrained('facilities', 'facility_id')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->date('booking_date');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('status')->default('Pending'); // Pending | Approved | Rejected
            $table->timestamp('created_at')->useCurrent();

            // Algorithm 2 (conflict detection) filters by facility_id + status + time range
            $table->index(['facility_id', 'status', 'start_time', 'end_time'], 'booking_requests_conflict_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
