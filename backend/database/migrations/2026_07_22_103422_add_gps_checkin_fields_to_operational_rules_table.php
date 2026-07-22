<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds GPS check-in verification fields to operational_rules — grouped
 * here rather than on facilities, since grace_period_minutes (the other
 * "how strict is check-in" setting) already lives on this table, not on
 * Facility.
 *
 * latitude/longitude are nullable: a facility with no GPS set yet simply
 * skips the distance check (see CheckInService) rather than blocking
 * every check-in until a manager configures it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_rules', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('grace_period_minutes');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            // Default 100m: phone GPS indoors is commonly 20-50m off, so a
            // tight radius (e.g. 10-20m) causes false rejections for
            // genuinely present users. 100m is a reasonable "same building"
            // tolerance — tune per facility if needed.
            $table->unsignedInteger('checkin_radius_meters')->default(100)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('operational_rules', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'checkin_radius_meters']);
        });
    }
};
