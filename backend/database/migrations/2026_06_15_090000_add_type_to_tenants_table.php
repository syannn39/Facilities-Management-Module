<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds an industry/type classifier to tenants so the frontend can render
     * a different Browse Facilities experience per property type — e.g. a
     * School tenant sees "Library Discussion Room, Auditorium, Sports Field",
     * a Residential tenant sees "Gym, BBQ Pit, Tennis Court, Function Hall".
     * This does not replace tenant_id isolation (still the security boundary);
     * it's purely a presentation/labelling hint.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('type')->default('residential')->after('name');
            // 'residential'  → apartment / condo / mixed residential complex
            // 'school'       → campus / educational institution
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
