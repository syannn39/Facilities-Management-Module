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
}
