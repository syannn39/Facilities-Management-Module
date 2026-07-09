<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ApprovalLog — Class Diagram (Figure 4.3.1) fields: log_id (PK),
     * request_id (FK), approver_id (FK), tier_level, action, remarks,
     * actioned_at.
     *
     * CHANGED FROM THE EARLIER ERD: that version had this table pointing
     * at booking_id instead, which forced booking_id to be nullable since
     * a Rejected request never produces a Booking row at all — meaning a
     * rejection's log entry had nothing to point at, and there was no way
     * to trace "how many times was this facility's request rejected"
     * directly from this table.
     *
     * Pointing at request_id instead (every BookingRequest exists at the
     * moment it's approved OR rejected) fixes that: both actions always
     * have something to reference, NOT NULL, no workaround needed.
     */
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->foreignId('request_id')->constrained('booking_requests', 'request_id')->onDelete('cascade');
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
