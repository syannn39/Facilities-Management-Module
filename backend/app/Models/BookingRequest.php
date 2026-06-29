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
     * The Booking this request produced once approved/confirmed. Null
     * while status is 'Pending', and stays null forever if 'Rejected'
     * (a rejected request never gets a Booking row).
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'request_id', 'request_id');
    }
}
