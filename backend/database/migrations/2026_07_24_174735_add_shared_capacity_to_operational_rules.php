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
        Schema::table('operational_rules', function (Blueprint $table) {
            // Adds the toggle, defaulting to false (exclusive booking)
            $table->boolean('is_shared_facility')->default(false)->after('advance_booking_limit');
            
            // Adds the slot limit, defaulting to 1 
            $table->integer('concurrent_booking_limit')->default(1)->after('is_shared_facility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operational_rules', function (Blueprint $table) {
            $table->dropColumn(['is_shared_facility', 'concurrent_booking_limit']);
        });
    }
};
