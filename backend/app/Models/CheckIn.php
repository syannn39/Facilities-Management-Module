<?php

namespace App\Models;

use App\Traits\HasLocalJsonDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    // NOT using BelongsToTenant — the ERD has no tenant_id on this table
    // either (scoped through booking_id -> Booking -> tenant_id instead).
    // Same reasoning as OperationalRule above.
    use HasLocalJsonDates; // checkin_time is a real datetime cast below — same UTC-serialization fix as Booking/BookingRequest

    protected $primaryKey = 'checkin_id';
    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'user_id',
        'checkin_time',
        'method', // 'QR' | 'Manual'
        'status', // 'Success' | 'Invalid_Location' | 'Outside_Window'
    ];

    protected $casts = [
        'checkin_time' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * processCheckIn() per Class Diagram — instance-level convenience
     * wrapper. The actual Algorithm 3 logic (QR token validation, arrival
     * window check, writing the audit row for failed attempts too) lives
     * in CheckInService::processQrCheckIn(), since it needs to look up
     * the Booking + Facility + OperationalRule chain and create a NEW
     * CheckIn row — an existing CheckIn instance calling this on itself
     * doesn't have enough context to redo that from scratch. This exists
     * so the model satisfies the diagram's method signature, but in
     * practice CheckInController calls the service directly.
     */
    public function processCheckIn(string $qrData): bool
    {
        try {
            app(\App\Services\CheckInService::class)->processQrCheckIn($this->booking_id, $qrData, $this->user_id);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * markAsNoShow() per Class Diagram — flags this check-in attempt
     * record as having missed its window. Distinct from
     * NoShowCancellationService (which cancels the Booking itself when
     * NO check-in attempt was ever made at all) — this is for the case
     * where a CheckIn row already exists (e.g. status='Outside_Window'
     * from a late scan) and needs to be explicitly marked as a no-show
     * outcome rather than left as a generic failed-attempt status.
     */
    public function markAsNoShow(): bool
    {
        return $this->update(['status' => 'Outside_Window']);
    }

    /**
     * isWithinWindow() per Class Diagram — true if this check-in's
     * recorded checkin_time falls within $windowMinutes of the linked
     * booking's start_time. Mirrors the same delta check
     * CheckInService::processQrCheckIn() runs before ever creating the
     * row, exposed here as a read-only check against an existing record
     * (e.g. for an audit screen reviewing past attempts).
     */
    public function isWithinWindow(int $windowMinutes = 15): bool
    {
        if (!$this->booking) {
            return false;
        }

        $diffInMinutes = $this->checkin_time->diffInMinutes($this->booking->start_time, false);

        return abs($diffInMinutes) <= $windowMinutes;
    }
}