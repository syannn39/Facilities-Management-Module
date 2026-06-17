<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            // Pending | Confirmed | Rejected | Checked_In | Cancelled_No_Show
            $table->string('status')->default('Pending');
            $table->text('purpose_of_use')->nullable();
            $table->integer('guest_count')->default(0);
            $table->timestamps();

            // Algorithm 2 (conflict detection) filters by facility_id + status + time range
            // on every booking attempt, so this composite index is what keeps that check fast.
            $table->index(['facility_id', 'status', 'start_time', 'end_time'], 'bookings_conflict_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
