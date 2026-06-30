<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $primaryKey = 'schedule_id';
    public $timestamps = false; // ERD lists no timestamp columns for this table

    protected $fillable = [
        'facility_id',
        'tenant_id',
        'booking_id',
        'slot_date',
        'start_time',
        'end_time',
        'is_available',
    ];

    protected $casts = [
        'slot_date' => 'date',
        'is_available' => 'boolean',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id', 'facility_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    /**
     * occupySlot() per Class Diagram — marks this slot as taken. In
     * practice SchedulingService::confirmBookingFromRequest() already
     * creates Schedule rows with is_available=false from the start (a
     * Schedule row only ever exists because a Booking occupies it), so
     * this exists for the case of re-marking an existing row, not as the
     * primary way slots get occupied.
     */
    public function occupySlot(): bool
    {
        return $this->update(['is_available' => false]);
    }

    /**
     * releaseSlot() per Class Diagram — frees this slot back up (e.g.
     * called when the linked Booking is cancelled, so the same time
     * range can be booked by someone else again).
     */
    public function releaseSlot(): bool
    {
        return $this->update(['is_available' => true]);
    }

    /**
     * checkAvailability() per Class Diagram — true if this specific
     * Schedule row is currently marked available. Note this checks one
     * row, not a date range — for "is facility X free at time Y on date
     * Z" across all bookings for that day, see
     * SchedulingService::getAvailability() instead, which is the actual
     * source of truth the booking modal calls (it computes slots
     * on-the-fly from Booking + Availability rather than relying on
     * pre-existing Schedule rows, since Schedule rows only exist for
     * slots that already got booked, not for every theoretically open slot).
     */
    public function checkAvailability(): bool
    {
        return $this->is_available;
    }
}
