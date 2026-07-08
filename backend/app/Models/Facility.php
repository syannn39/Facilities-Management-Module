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
    public $timestamps = false; // Class Diagram lists only created_at for this table

    protected $fillable = [
        'name',
        'category',
        'status',
        'image_url',
        'workflow_tier_id'
    ];

    // Relation methods renamed to match the Class Diagram's exact names
    // (getOperationalRule/getAvailability/getBookingRequests) — every
    // caller across the codebase (SchedulingService, CheckInService,
    // OperationalRuleController, frontend JSON keys) has been updated to
    // match; see CHANGES_UML_ALIGNMENT.md for the full list.

    public function getOperationalRule(): HasOne
    {
        return $this->hasOne(OperationalRule::class, 'facility_id', 'facility_id');
    }

    /**
     * getAvailability() per Class Diagram — note the diagram names this
     * singular ("Availability") even though it returns a collection of
     * Availability rows; kept as a HasMany since one facility can have
     * many blocked-slot rows.
     */
    public function getAvailability(): HasMany
    {
        return $this->hasMany(Availability::class, 'facility_id', 'facility_id');
    }

    public function getBookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class, 'facility_id', 'facility_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'facility_id', 'facility_id');
    }

    /**
     * checkActiveBookings() per Class Diagram — true if this facility has
     * any booking currently in progress or upcoming (Confirmed and not
     * yet ended). Used, for example, before allowing a manager to set
     * status to 'maintenance' — you probably don't want to silently
     * disable a facility someone's mid-use of.
     */
    public function checkActiveBookings(): bool
    {
        return $this->bookings()
            ->where('status', 'Confirmed')
            ->where('end_time', '>', now())
            ->exists();
    }

    /**
     * updateStatus() per Class Diagram — validates the target status is
     * one of the three the facilities.status column actually supports
     * before writing it (the migration's column comment lists
     * 'active'|'inactive'|'maintenance'; this is where that's enforced in
     * code rather than just left as a comment).
     */
    public function updateStatus(string $status): bool
    {
        if (!in_array($status, ['active', 'inactive', 'maintenance'], true)) {
            return false;
        }

        return $this->update(['status' => $status]);
    }
}
