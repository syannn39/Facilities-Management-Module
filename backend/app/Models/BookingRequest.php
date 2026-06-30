<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingRequest extends Model
{
    use BelongsToTenant; // Enforces Automated Tenant Isolation

    protected $primaryKey = 'request_id';
    public $timestamps = false; // ERD lists only created_at for this table

    protected $fillable = [
        'booking_id',
        'tenant_id',
        'facility_id',
        'user_id',
        'booking_date',
        'start_time',
        'end_time',
        'status',          // Pending | Approved | Rejected
        'purpose_of_use',  // not in ERD, see migration note
        'guest_count',     // not in ERD, see migration note
    ];

    protected $casts = [
        'booking_date' => 'date',
        'start_time'   => 'datetime',
        'end_time'     => 'datetime',
        'guest_count'  => 'integer',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id', 'facility_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * getBooking() per Class Diagram (renamed from booking() — every
     * caller across BookingController and the frontend's MyBookings.jsx
     * has been updated to match; see CHANGES_UML_ALIGNMENT.md).
     *
     * The Booking this request produced once approved/confirmed. Null
     * while status is 'Pending', and stays null forever if 'Rejected'
     * (a rejected request never gets a Booking row).
     */
    public function getBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function getNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'request_id', 'request_id');
    }

    /**
     * getApprovalLogs() per Class Diagram — was entirely missing before
     * (this relation only made sense once ApprovalLog pointed at
     * request_id rather than booking_id — see migration note on
     * approval_logs for why that change was made).
     */
    public function getApprovalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'request_id', 'request_id');
    }

    /**
     * updateStatus() per Class Diagram — validates the target status is
     * one BookingRequest.status actually supports before writing it.
     */
    public function updateStatus(string $status): bool
    {
        if (!in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
            return false;
        }

        return $this->update(['status' => $status]);
    }
}
