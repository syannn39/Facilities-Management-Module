<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OperationalRule — ERD fields: rule_id (PK), facility_id (FK),
     * max_capacity, opening_time, closing_time, advance_booking_limit,
     * approval_tier, updated_at. Matches the ERD exactly.
     *
     * No tenant_id on this table — the ERD scopes OperationalRule through
     * facility_id → Facility → tenant_id rather than duplicating tenant_id
     * directly on every table.
     *
     * NOTE: grace_period_minutes was removed to strictly match the ERD.
     * That field was backing FR5 / Algorithm 3's check-in grace window —
     * if that feature is still needed, add the field back to the ERD
     * itself, or find another home for it in your app logic.
     */
    public function up(): void
    {
        Schema::create('operational_rules', function (Blueprint $table) {
            $table->id('rule_id');
            $table->foreignId('facility_id')->constrained('facilities', 'facility_id')->onDelete('cascade');
            $table->integer('max_capacity');
            $table->time('opening_time')->default('08:00:00');
            $table->time('closing_time')->default('22:00:00');
            // How many days in advance a booking may be made (e.g. 30 = can't book more than 30 days out)
            $table->integer('advance_booking_limit')->default(30);
            $table->integer('approval_tier')->default(0); // 0: no approval, >0: requires approval
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_rules');
    }
};
