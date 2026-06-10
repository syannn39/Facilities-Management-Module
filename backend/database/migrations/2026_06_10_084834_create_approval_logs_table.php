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
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            //links to SY's booking requests
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('approver_id'); 
            $table->string('action'); // approved or rejected
            $table->text('remarks')->nullable();
            $table->integer('tier_level'); // which tier this action satisfied
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
