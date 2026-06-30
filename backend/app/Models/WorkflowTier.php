<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTier extends Model
{
    protected $primaryKey = 'tier_id';

    protected $fillable = [
        'rule_id',
        'tier_level',
        'assigned_role', // e.g. 'Manager', 'Admin'
    ];

    protected $casts = [
        'tier_level' => 'integer',
    ];

    public function getOperationalRule(): BelongsTo
    {
        return $this->belongsTo(OperationalRule::class, 'rule_id', 'rule_id');
    }

    /**
     * getApprovalByRole() per Class Diagram — the ApprovalLog entry (if
     * any) where a user with this tier's assigned_role acted on a given
     * request. Lets a caller check "has someone with this tier's role
     * already weighed in on this specific request" without WorkflowService
     * having to reimplement that lookup itself.
     */
    public function getApprovalByRole(int $requestId): ?ApprovalLog
    {
        return ApprovalLog::where('request_id', $requestId)
            ->where('tier_level', $this->tier_level)
            ->first();
    }

    /**
     * getNextTier() per Class Diagram — the WorkflowTier one level above
     * this one for the same OperationalRule, or null if this is already
     * the last configured tier.
     */
    public function getNextTier(): ?self
    {
        return self::where('rule_id', $this->rule_id)
            ->where('tier_level', '>', $this->tier_level)
            ->orderBy('tier_level')
            ->first();
    }
}
