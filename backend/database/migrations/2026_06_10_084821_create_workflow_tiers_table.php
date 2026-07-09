<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WorkflowTier — ERD fields: tier_id (PK), rule_id (FK), tier_level,
     * assigned_role.
     *
     * Renamed from the previous version: operational_rule_id → rule_id
     * (matches OperationalRule's PK name), role_required (integer) →
     * assigned_role (string) — the ERD's assigned_role is clearly meant to
     * hold a role name like "Manager", not a numeric code.
     */
    public function up(): void
    {
        Schema::create('workflow_tiers', function (Blueprint $table) {
            $table->id('tier_id');
            $table->foreignId('rule_id')->constrained('operational_rules', 'rule_id')->onDelete('cascade');
            $table->integer('tier_level'); // 1: first tier, 2: second tier, etc.
            $table->string('assigned_role'); // e.g., 'Manager', 'Admin'
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_tiers');
    }
};
