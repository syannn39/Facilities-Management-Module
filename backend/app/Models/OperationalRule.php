<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalRule extends Model
{
    // NOT using BelongsToTenant here: this table has no tenant_id column
    // under the ERD (it's scoped through facility_id -> Facility ->
    // tenant_id instead, not duplicated directly on every table). Applying
    // the trait's TenantScope here would crash every query with "no such
    // column: operational_rules.tenant_id" — the exact bug that trait was
    // originally added to fix on a different table, just inverted.
    //
    // Practical effect: a query like OperationalRule::all() is NOT
    // automatically tenant-scoped. Anywhere this matters, go through
    // $facility->operationalRule (Facility IS tenant-scoped, so you only
    // ever reach an OperationalRule belonging to a facility you were
    // already allowed to see) rather than querying OperationalRule directly.

    protected $primaryKey = 'rule_id';
    public $timestamps = false;
    const UPDATED_AT = 'updated_at'; // ERD lists only updated_at (no created_at)

    protected $fillable = [
        'facility_id',
        'max_capacity',
        'opening_time',
        'closing_time',
        'advance_booking_limit',
        'approval_tier',
        'grace_period_minutes', // not in ERD — see migration note
    ];

    protected $casts = [
        'max_capacity' => 'integer',
        'advance_booking_limit' => 'integer',
        'approval_tier' => 'integer',
        'grace_period_minutes' => 'integer',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id', 'facility_id');
    }

    public function workflowTiers(): HasMany
    {
        return $this->hasMany(WorkflowTier::class, 'rule_id', 'rule_id');
    }
}
