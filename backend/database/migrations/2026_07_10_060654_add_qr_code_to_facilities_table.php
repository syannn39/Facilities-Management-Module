<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->uuid('qr_code_token')->nullable()->unique()->after('image_url');
            $table->timestamp('qr_code_generated_at')->nullable()->after('qr_code_token');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn(['qr_code_token', 'qr_code_generated_at']);
        });
    }
};
