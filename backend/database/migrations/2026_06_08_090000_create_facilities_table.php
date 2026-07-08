<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Facility — ERD fields: facility_id (PK), tenant_id (FK), name,
     * category, status, image_url, created_at.
     *
     * `approval_tier` previously lived on this table too (a holdover from
     * before OperationalRule existed) — the ERD only has it on
     * OperationalRule, so it's removed here to avoid two tables disagreeing
     * about which one is authoritative. SchedulingService now reads
     * approval_tier from facility->getOperationalRule->approval_tier instead.
     */
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id('facility_id');
            $table->foreignId('tenant_id')->constrained('tenants', 'tenant_id')->onDelete('cascade');
            $table->string('name'); // e.g., "Gym", "BBQ Pit", "Function Hall"
            $table->string('category')->nullable(); // e.g., "Sports", "Recreation", "Event Space"
            $table->integer('capacity')->nullable();
            $table->string('status')->default('active'); // 'active' | 'inactive' | 'maintenance'
            $table->string('image_url')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
