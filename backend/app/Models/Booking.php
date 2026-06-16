<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use BelongsToTenant; // Enforces Automated Tenant Isolation

    protected $fillable = [
        'user_id',
        'facility_id',
        'start_time',
        'end_time',
        'status',
        'purpose_of_use',
        'guest_count',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * Get the user that owns the booking.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the facility reserved by this booking.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the check-in data associated with this booking.
     */
    public function checkIn(): HasOne
    {
        return $this->hasOne(CheckIn::class);
    }
}