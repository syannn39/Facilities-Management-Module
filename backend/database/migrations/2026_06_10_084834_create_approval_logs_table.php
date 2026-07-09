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
     * NOTE: reverted to point at booking_id, matching the ERD exactly.
     * booking_id is nullable here because of a real gap the ERD doesn't
     * account for: a Rejected BookingRequest never produces a Booking row,
     * so a rejection log entry has nothing non-null to point at. If you
     * want every rejection traceable without a NULL FK, the ERD itself
     * needs to point ApprovalLog at request_id instead — flagging this
     * as a design question for you and your teammate, not just silently
     * "fixing" it against your explicit instruction to match the diagram.
     */
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->foreignId('booking_id')->nullable()->constrained('bookings', 'booking_id')->onDelete('cascade');
            $table->foreignId('approver_id')->constrained('users', 'user_id')->onDelete('cascade');
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
