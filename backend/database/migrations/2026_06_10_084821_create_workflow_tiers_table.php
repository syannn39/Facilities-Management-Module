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
        Schema::create('workflow_tiers', function (Blueprint $table) {
            $table->id();
            // links to operational rules
            $table->foreignId('operational_rule_id')->constrained('operational_rules')->onDelete('cascade');
            $table->integer('tier_level'); // 1: first tier, 2: second tier, etc.
            $table->integer('role_required'); // e.g., 'Admin', 'Property Manager'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_tiers');
    }
};
