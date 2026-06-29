<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use BelongsToTenant; // Enforces Automated Tenant Isolation

    protected $primaryKey = 'facility_id';
    public $timestamps = false; // ERD lists only created_at for this table

    protected $fillable = [
        'name',
        'category',
        'status',
        'image_url',
    ];

    /**
     * Get the operational rule configuration for this facility.
     * approval_tier now lives only on OperationalRule (the ERD doesn't
     * duplicate it here), so checking whether a facility needs approval
     * means going through this relation: $facility->operationalRule->approval_tier.
     */
    public function operationalRule(): HasOne
    {
        return $this->hasOne(OperationalRule::class, 'facility_id', 'facility_id');
    }

    /**
     * Booking requests made against this facility (the ERD puts
     * facility_id on BookingRequest, not directly on Booking — to reach
     * this facility's actual confirmed Bookings, go through
     * bookingRequests()->bookings() or Booking::whereHas('bookingRequest', ...)).
     */
    public function bookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class, 'facility_id', 'facility_id');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class, 'facility_id', 'facility_id');
    }
}
