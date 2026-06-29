<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalLog extends Model
{
    protected $primaryKey = 'log_id';
    public $timestamps = false;
    const UPDATED_AT = null;
    const CREATED_AT = null; // ERD's only timestamp here is actioned_at, handled manually below

    protected $fillable = [
        'booking_id',
        'approver_id',
        'tier_level',
        'action', // 'Approved' | 'Rejected'
        'remarks',
        'actioned_at',
    ];

    protected $casts = [
        'tier_level' => 'integer',
        'actioned_at' => 'datetime',
    ];

    /**
     * The booking this approval/rejection produced — NULL for a rejection,
     * since a rejected request never gets a Booking row under the ERD
     * (see migration note on approval_logs for the reasoning).
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'booking_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
