<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User — ERD fields: user_id (PK, already `id` from Laravel's base
     * users migration), tenant_id (FK), name, role, email, password_hash
     * (Laravel's `password` column — kept as `password` rather than
     * renamed, since Auth/Sanctum/the 'hashed' cast all key off that exact
     * column name; renaming it would break authentication, not just
     * cosmetics), phone_number, created_at (already present from the base
     * migration).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Every user is bound to exactly one tenant property.
            $table->foreignId('tenant_id')->nullable()->constrained('tenants', 'tenant_id')->onDelete('cascade');
            $table->string('role')->default('Resident'); // 'Resident' or 'Manager'
            $table->string('phone_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'role', 'phone_number']);
        });
    }
};
