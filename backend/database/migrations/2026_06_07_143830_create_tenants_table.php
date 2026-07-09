<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant — ERD fields: tenant_id (PK), tenant_name, contact_email,
     * address, created_at.
     *
     * `type` ('residential' | 'school') is kept even though it's not in the
     * ERD — it's a presentation-only hint added for the multi-tenant
     * industry theming feature (Browse Facilities shows different framing
     * per industry) and has no bearing on tenant_id isolation, which
     * remains the actual security boundary.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id('tenant_id');
            $table->string('tenant_name');
            $table->string('contact_email')->nullable();
            $table->string('address')->nullable();
            $table->string('type')->default('residential'); // 'residential' | 'school' — not in ERD, see note above
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
