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
        Schema::create('operational_rules', function (Blueprint $table) {
            $table->id();
            // Required by the BelongsToTenant trait / TenantScope (Chapter 4: every
            // critical table is scoped by tenant_id). This was missing before, which
            // crashed any query on this table with "no such column: operational_rules.tenant_id".
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            // link to SY's facilities table
            $table->unsignedBigInteger('facility_id');
            // governance limits from chp4 algorithms
            $table->integer('max_capacity');
            $table->integer('approval_tier')->default(0); // 0: no approval, >0: requires approval from multi-tier workflow
            $table->integer('grace_period_minutes')->default(15); // grace period for late check-in or early check-out
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_rules');
    }
};
