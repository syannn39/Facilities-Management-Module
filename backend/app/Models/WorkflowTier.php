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

    public function operationalRule(): BelongsTo
    {
        return $this->belongsTo(OperationalRule::class, 'rule_id', 'rule_id');
    }
}
