<?php

namespace App\Models;

use App\Models\Booking;
use App\Models\OperationalRule;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use BelongsToTenant; // Enforces Automated Tenant Isolation

    protected $fillable = [
        'name',
        'description',
        'approval_tier',
    ];

    /**
     * Get the operational rules associated with this facility.
     */
    public function operationalRule(): HasOne
    {
        return $this->hasOne(\App\Models\OperationalRule::class);
    }

    /**
     * Get all bookings associated with this facility.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}