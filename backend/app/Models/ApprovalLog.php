<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalLog extends Model
{
    protected $primaryKey = 'log_id';
    public $timestamps = false;
    const UPDATED_AT = null;
    const CREATED_AT = null; // only timestamp here is actioned_at, handled manually

    protected $fillable = [
        'request_id',
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
     * getBookingRequest() per Class Diagram Figure 4.3.1 — every approval
     * AND rejection has a request_id (unlike the previous booking_id
     * design, this is never null: the BookingRequest exists at the moment
     * either decision is made).
     */
    public function getBookingRequest(): BelongsTo
    {
        return $this->belongsTo(BookingRequest::class, 'request_id', 'request_id');
    }

    public function getApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * logAction() per Class Diagram — writes one ApprovalLog row recording
     * an approve/reject decision. Thin wrapper kept here (rather than only
     * in WorkflowService) so the model itself can satisfy the diagram's
     * method signature; WorkflowService::processApproval()/processRejection()
     * call this internally rather than duplicating the create() call.
     */
    public static function logAction(int $requestId, int $approverId, int $tierLevel, string $action, ?string $remarks = null): self
    {
        return self::create([
            'request_id'   => $requestId,
            'approver_id'  => $approverId,
            'tier_level'   => $tierLevel,
            'action'       => $action,
            'remarks'      => $remarks,
            'actioned_at'  => now(),
        ]);
    }
}
