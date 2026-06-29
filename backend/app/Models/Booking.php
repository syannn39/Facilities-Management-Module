<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Booking extends Model
{
    use BelongsToTenant; // Enforces Automated Tenant Isolation

    protected $primaryKey = 'booking_id';
    public $timestamps = false; // ERD lists only created_at for this table

    protected $fillable = [
        'request_id',
        'user_id',
        'booking_type', // 'Instant' | 'Request'
        'booking_date',
        'start_time',
        'end_time',
        'status',       // Confirmed | Checked_In | Cancelled_No_Show
    ];

    protected $casts = [
        'booking_date' => 'date',
        'start_time'   => 'datetime',
        'end_time'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The originating request — this is also how to reach the facility:
     * $booking->bookingRequest->facility (facility_id lives on
     * BookingRequest under the ERD, not directly on Booking).
     */
    public function bookingRequest(): BelongsTo
    {
        return $this->belongsTo(BookingRequest::class, 'request_id', 'request_id');
    }

    /**
     * Convenience accessor so existing code that did $booking->facility
     * keeps working without every caller needing to know about the
     * BookingRequest indirection. Read-only — there's no facility_id
     * column on this table to write through.
     */
    public function facility(): HasOneThrough
    {
        return $this->hasOneThrough(
            Facility::class,
            BookingRequest::class,
            'booking_id',   // FK on booking_requests pointing back to this booking
            'facility_id',  // FK on facilities
            'booking_id',   // local key on bookings
            'facility_id',  // local key on booking_requests
        );
    }

    public function checkIn(): HasOne
    {
        return $this->hasOne(CheckIn::class, 'booking_id', 'booking_id');
    }
}
