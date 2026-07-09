<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User — ERD fields: user_id (PK), tenant_id (FK), name, role, email,
     * password_hash, phone_number, created_at.
     *
     * IMPORTANT: the base users migration now names the PK `user_id` and
     * the password column `password_hash` to strictly match the ERD. This
     * requires corresponding changes in App\Models\User (which wasn't
     * included in this upload) — see the summary notes for what to update
     * there (primary key, auth password field, guarded/fillable list).
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
