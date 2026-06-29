<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CheckIn — ERD fields: checkin_id (PK), booking_id (FK), user_id (FK),
     * checkin_time, method, status.
     *
     * tenant_id removed (not in the ERD — scoped through booking_id →
     * Booking → tenant_id instead). user_id, method, and status are new —
     * user_id lets us query "did this specific user check in" directly
     * instead of joining through Booking every time; method records how
     * the check-in happened (e.g. 'QR' for now, room for 'Manual' later if
     * a manager ever needs to check someone in by hand); status records
     * the outcome ('Success' | 'Invalid_Location' | 'Outside_Window') for
     * an audit trail of failed/rejected scan attempts, not just successful ones.
     */
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id('checkin_id');
            // One booking can only ever be successfully checked into once.
            $table->foreignId('booking_id')->unique()->constrained('bookings', 'booking_id')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->dateTime('checkin_time');
            $table->string('method')->default('QR'); // 'QR' | 'Manual'
            $table->string('status')->default('Success'); // 'Success' | 'Invalid_Location' | 'Outside_Window'
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
