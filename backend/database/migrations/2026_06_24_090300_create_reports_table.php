<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Report — ERD fields: report_id (PK), tenant_id (FK), generated_by
     * (FK), report_type, date_from, date_to, file_url, generated_at.
     *
     * Records a generated facility-usage/booking-history report (e.g. a
     * manager exporting "all bookings for March 2026" as a PDF/CSV).
     * file_url points at wherever that export was stored once generated.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->foreignId('tenant_id')->constrained('tenants', 'tenant_id')->onDelete('cascade');
            $table->foreignId('generated_by')->constrained('users', 'id')->onDelete('cascade');
            $table->string('report_type'); // e.g., 'Booking_History', 'No_Show_Summary', 'Facility_Usage'
            $table->date('date_from');
            $table->date('date_to');
            $table->string('file_url')->nullable();
            $table->timestamp('generated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
