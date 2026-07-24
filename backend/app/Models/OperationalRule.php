<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class OperationalRule extends Model
{
    // NOT using BelongsToTenant here: this table has no tenant_id column
    // (it's scoped through facility_id -> Facility -> tenant_id instead,
    // not duplicated directly on every table). Applying the trait's
    // TenantScope here would crash every query with "no such column:
    // operational_rules.tenant_id".
    //
    // Practical effect: a query like OperationalRule::all() is NOT
    // automatically tenant-scoped. Anywhere this matters, go through
    // $facility->getOperationalRule (Facility IS tenant-scoped) rather
    // than querying OperationalRule directly.

    protected $primaryKey = 'rule_id';
    public $timestamps = false;
    const UPDATED_AT = 'updated_at'; // Class Diagram lists only updated_at (no created_at)

    protected $fillable = [
        'facility_id',
        'max_capacity',
        'opening_time',
        'closing_time',
        'advance_booking_limit',
        'approval_tier',
        'grace_period_minutes', 
        'latitude',             // GPS check-in verification — see migration note
        'longitude',
        'checkin_radius_meters',
        'is_shared_facility',   
        'concurrent_booking_limit',
    ];

    protected $casts = [
        'max_capacity' => 'integer',
        'advance_booking_limit' => 'integer',
        'approval_tier' => 'integer',
        'grace_period_minutes' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'checkin_radius_meters' => 'integer',
        'is_shared_facility' => 'boolean',
        'concurrent_booking_limit' => 'integer',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id', 'facility_id');
    }

    public function getWorkflowTiers(): HasMany
    {
        return $this->hasMany(WorkflowTier::class, 'rule_id', 'rule_id');
    }

    /**
     * isAutoApprove() per Class Diagram — true when no manager review is
     * needed at all (approval_tier 0).
     */
    public function isAutoApprove(): bool
    {
        return $this->approval_tier === 0;
    }

    /**
     * validateCapacity()/validateBookingTime()/validateAdvanceLimit() per
     * Class Diagram — thin wrappers delegating to RuleEngineService so
     * the actual rule logic lives in exactly one place (the service),
     * not duplicated between model and service layers. validateBookingTime()
     * maps to RuleEngineService::validateOperatingHours() — the diagram's
     * name for this method doesn't quite match the service layer
     * diagram's name for the same check (validateOperatingHours), kept as
     * one real implementation under that name with this model method
     * delegating to it under the name this diagram uses.
     */
    public function validateCapacity(int $guestCount): bool
    {
        return app(\App\Services\RuleEngineService::class)->validateCapacity($this, $guestCount);
    }

    public function validateBookingTime(Carbon $startTime, Carbon $endTime): bool
    {
        return app(\App\Services\RuleEngineService::class)->validateOperatingHours($this, $startTime, $endTime);
    }

    public function validateAdvanceLimit(Carbon $startTime): bool
    {
        return app(\App\Services\RuleEngineService::class)->validateAdvanceLimit($this, $startTime);
    }
}
