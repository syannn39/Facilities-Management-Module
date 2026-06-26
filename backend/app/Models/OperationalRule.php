<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalRule extends Model
{
    use BelongsToTenant; // Enforces Automated Tenant Isolation

    protected $fillable = [
        'facility_id',
        'max_capacity',
        'approval_tier',
        'grace_period_minutes',
    ];

    protected $casts = [
        'max_capacity' => 'integer',
        'approval_tier' => 'integer',
        'grace_period_minutes' => 'integer',
    ];

    /**
     * Get the facility that owns this operational rule configuration.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
