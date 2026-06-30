<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $primaryKey = 'tenant_id';

    // Class Diagram lists only created_at for this table (no updated_at)
    // — disable Eloquent's automatic updated_at handling so it doesn't
    // try to write to a column that doesn't exist.
    public $timestamps = false;

    protected $fillable = [
        'tenant_name',
        'contact_email',
        'address',
        'type', // 'residential' | 'school' — not in either diagram, see migration note
    ];

    // Relation methods renamed to match the Class Diagram's exact method
    // names (getUsers/getFacilities/getBookingRequests/getReports) rather
    // than Laravel's usual bare-name convention (users()/facilities()/...)
    // — this is a deliberate, full commitment to the diagram's naming, not
    // a partial alias; there's only one name per relation now.

    public function getUsers(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id', 'tenant_id');
    }

    public function getFacilities(): HasMany
    {
        return $this->hasMany(Facility::class, 'tenant_id', 'tenant_id');
    }

    public function getBookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class, 'tenant_id', 'tenant_id');
    }

    /**
     * getReports() per Class Diagram — was entirely missing before (no
     * relation existed in either direction between Tenant and Report).
     */
    public function getReports(): HasMany
    {
        return $this->hasMany(Report::class, 'tenant_id', 'tenant_id');
    }
}
