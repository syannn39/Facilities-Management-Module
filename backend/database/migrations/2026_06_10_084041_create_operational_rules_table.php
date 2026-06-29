<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * OperationalRule — ERD fields: rule_id (PK), facility_id (FK),
     * max_capacity, opening_time, closing_time, advance_booking_limit,
     * approval_tier, updated_at (the ERD lists only updated_at here, not
     * created_at — kept that way to match).
     *
     * No tenant_id on this table — the ERD scopes OperationalRule through
     * facility_id → Facility → tenant_id rather than duplicating tenant_id
     * directly on every table. (An earlier version of this migration did
     * add tenant_id directly here as a quick fix for a TenantScope crash;
     * this rewrite removes it again to match the ERD, and the model's
     * tenant scoping is adjusted accordingly — see OperationalRule.php.)
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

            // NOT in the ERD — flagging this for you and your teammate.
            // FR5 / Algorithm 3 (Check-in Validation) in your report needs a
            // per-facility check-in grace window (the "15-minute arrival
            // window"), and nothing in the ERD's CheckIn or OperationalRule
            // entities has a field to store that number. Kept here so the
            // feature keeps working; worth adding to the ERD itself so the
            // diagram and the actual schema agree.
            $table->integer('grace_period_minutes')->default(15);

            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_rules');
    }
};
