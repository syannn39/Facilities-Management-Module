<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Exception;

class Booking extends Model
{
    use BelongsToTenant; // Enforces Automated Tenant Isolation

    protected $primaryKey = 'booking_id';
    public $timestamps = false; // Class Diagram lists only created_at for this table

    protected $fillable = [
        'request_id',
        'facility_id', // direct column per Class Diagram (was only reachable via BookingRequest before)
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

    public function bookingRequest(): BelongsTo
    {
        return $this->belongsTo(BookingRequest::class, 'request_id', 'request_id');
    }

    /**
     * facility_id is now a direct column (Class Diagram), so this is a
     * simple belongsTo — no more hasOneThrough indirection via
     * BookingRequest like the previous ERD version required.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id', 'facility_id');
    }

    /**
     * getCheckIn() per Class Diagram.
     */
    public function getCheckIn(): HasOne
    {
        return $this->hasOne(CheckIn::class, 'booking_id', 'booking_id');
    }

    /**
     * getSchedule() per Class Diagram — the historical Schedule row
     * written when this Booking was confirmed (see
     * SchedulingService::confirmBookingFromRequest()).
     */
    public function getSchedule(): HasOne
    {
        return $this->hasOne(Schedule::class, 'booking_id', 'booking_id');
    }

    /**
     * confirm() per Class Diagram — transitions a Booking into the
     * Confirmed state. In practice almost every Booking is already
     * Confirmed at creation time (SchedulingService sets that directly),
     * so this exists mainly for completeness/symmetry with cancel(), and
     * for the case of explicitly re-confirming after some other state.
     * Delegates to StateMachineService so the actual list of valid
     * transitions lives in one place, not duplicated here.
     */
    public function confirm(): bool
    {
        return app(\App\Services\StateMachineService::class)->transition($this, 'Confirmed');
    }

    /**
     * cancel() per Class Diagram — cancels a booking that hasn't been
     * checked into yet. Returns false (does not throw) if the booking is
     * already Checked_In, since that's a normal "can't do that" outcome a
     * caller should be able to check via the return value rather than
     * having to catch an exception for routine UI flows.
     *
     * NOTE: bookings.status only has Confirmed | Checked_In |
     * Cancelled_No_Show — there's no distinct "user voluntarily cancelled"
     * state separate from "system auto-cancelled due to no-show". This
     * reuses Cancelled_No_Show for both as a stopgap; if you want My
     * Bookings to show a different message for "you cancelled this" vs
     * "you missed check-in", the status enum needs a fourth value (e.g.
     * 'Cancelled_By_User') added to both this column and StateMachineService's
     * transition table.
     */
    public function cancel(): bool
    {
        if ($this->status === 'Checked_In') {
            return false;
        }

        return app(\App\Services\StateMachineService::class)->transition($this, 'Cancelled_No_Show');
    }
}
