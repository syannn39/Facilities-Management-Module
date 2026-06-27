<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds per-facility operating hours so the booking modal can generate
     * a fixed list of 2-hour slots between open_time and close_time, and the
     * availability endpoint can mark which of those slots are already taken.
     */
    public function up(): void
    {
        Schema::table('operational_rules', function (Blueprint $table) {
            $table->time('open_time')->default('08:00:00')->after('grace_period_minutes');
            $table->time('close_time')->default('22:00:00')->after('open_time');
        });
    }

    public function down(): void
    {
        Schema::table('operational_rules', function (Blueprint $table) {
            $table->dropColumn(['open_time', 'close_time']);
        });
    }
};
