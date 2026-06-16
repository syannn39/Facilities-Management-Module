<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    use BelongsToTenant; // Enforces Automated Tenant Isolation

    protected $fillable = [
        'booking_id',
        'checkin_time',
    ];

    protected $casts = [
        'checkin_time' => 'datetime',
    ];

    /**
     * Get the booking reservation tied to this check-in entry.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}