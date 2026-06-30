<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $primaryKey = 'notification_id';
    public $timestamps = false;
    const CREATED_AT = null;
    const UPDATED_AT = null; // ERD's only timestamp here is sent_at, handled manually

    protected $fillable = [
        'tenant_id',
        'user_id',
        'request_id',
        'type',
        'subject',
        'message_body',
        'status', // 'Unread' | 'Read'
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookingRequest(): BelongsTo
    {
        return $this->belongsTo(BookingRequest::class, 'request_id', 'request_id');
    }

    /**
     * send() per Class Diagram — marks this notification as sent (status
     * already starts as 'Unread' when created by NotificationService, so
     * this is mainly a no-op / status assertion in normal flow; exists so
     * a retry/resend path can call it explicitly to record the send event).
     */
    public function send(): bool
    {
        return $this->update(['status' => 'Unread', 'sent_at' => now()]);
    }

    /**
     * markAsRead() per Class Diagram — called by NotificationController
     * when the user explicitly marks a notification as read.
     */
    public function markAsRead(): bool
    {
        return $this->update(['status' => 'Read']);
    }

    /**
     * markAsFailed() per Class Diagram — marks this notification as failed
     * to deliver. 'Failed' is not a status currently in the migration's
     * default set ('Unread' | 'Read') — it would need to be added to the
     * column enum/check if strict DB-level validation is enforced. Treated
     * here as a string write since the column is a plain varchar with no
     * constraint, and this method only gets called on genuine delivery
     * failures (e.g. if a queued job to push to an external channel errors
     * out).
     */
    public function markAsFailed(): bool
    {
        return $this->update(['status' => 'Failed']);
    }
}
