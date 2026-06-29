<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    // NOT using BelongsToTenant — the ERD has no tenant_id on this table
    // either (scoped through booking_id -> Booking -> tenant_id instead).
    // Same reasoning as OperationalRule above.

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
}
