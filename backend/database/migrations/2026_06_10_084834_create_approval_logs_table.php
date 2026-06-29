<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ApprovalLog — ERD fields: log_id (PK), booking_id (FK), approver_id
     * (FK), tier_level, action, remarks, actioned_at.
     *
     * Renamed from the previous version's `request_id` to `booking_id` to
     * match the ERD exactly. Note this is a real semantic change, not just
     * a rename: the ERD has ApprovalLog pointing at Booking, meaning the
     * approval decision is logged against the row created once a request
     * is approved — but a request that's REJECTED never produces a Booking
     * row at all (see BookingRequest.status='Rejected'). So a rejection
     * can't be logged here under the ERD's literal design.
     *
     * Handled by allowing booking_id to be nullable: an approval (action=
     * 'Approved') logs the real booking_id once it's created; a rejection
     * (action='Rejected') logs booking_id=NULL, since there's no Booking
     * to point at — the request itself (and its rejection reason) lives
     * entirely in BookingRequest.status / remarks here.
     */
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->foreignId('booking_id')->nullable()->constrained('bookings', 'booking_id')->onDelete('cascade');
            $table->foreignId('approver_id')->constrained('users', 'id')->onDelete('cascade');
            $table->integer('tier_level'); // which workflow tier this action satisfied
            $table->string('action'); // 'Approved' | 'Rejected'
            $table->text('remarks')->nullable();
            $table->timestamp('actioned_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
