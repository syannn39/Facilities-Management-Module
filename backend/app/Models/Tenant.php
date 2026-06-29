<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $primaryKey = 'tenant_id';

    // ERD lists only created_at for this table (no updated_at) — disable
    // Eloquent's automatic updated_at handling so it doesn't try to write
    // to a column that doesn't exist.
    public $timestamps = false;

    protected $fillable = [
        'tenant_name',
        'contact_email',
        'address',
        'type', // 'residential' | 'school' — not in ERD, see migration note
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id', 'tenant_id');
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class, 'tenant_id', 'tenant_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'tenant_id', 'tenant_id');
    }

    public function bookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class, 'tenant_id', 'tenant_id');
    }
}
