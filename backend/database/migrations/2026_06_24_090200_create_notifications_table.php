<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notification — ERD fields: notification_id (PK), tenant_id (FK),
     * user_id (FK), request_id (FK), type, subject, message_body, status,
     * sent_at.
     *
     * This replaces Laravel's built-in Notifiable trait/notifications
     * table as the system of record for in-app messages (booking
     * confirmations, rejection reasons, no-show alerts) — written
     * explicitly by app code instead of through Laravel's queued
     * notification channels, so every message has a request_id to trace
     * back to.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->foreignId('tenant_id')->constrained('tenants', 'tenant_id')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users', 'user_id')->onDelete('cascade');
            $table->foreignId('request_id')->nullable()->constrained('booking_requests', 'request_id')->onDelete('cascade');
            $table->string('type'); // 'Booking_Confirmed' | 'Request_Pending' | 'Request_Approved' | 'Request_Rejected' | 'No_Show'
            $table->string('subject');
            $table->text('message_body');
            $table->string('status')->default('Unread'); // 'Unread' | 'Read'
            $table->timestamp('sent_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
