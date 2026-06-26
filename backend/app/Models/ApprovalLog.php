<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalLog extends Model
{
    protected $fillable = [
        'request_id',
        'approver_id',
        'action',
        'remarks',
        'tier_level',
    ];

    /**
     * The booking (request) this log entry refers to.
     * NOTE: the approval_logs migration stores this as `request_id`
     * (there is no separate booking_requests table — Booking doubles
     * as the request record), so the FK must be specified explicitly.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'request_id');
    }

    /**
     * The manager who approved/rejected the booking.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
