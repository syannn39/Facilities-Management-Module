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
}
