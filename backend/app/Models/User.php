<?php

namespace App\Models;

use App\Traits\HasLocalJsonDates;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory;
    // No $timestamps = false override here, so Eloquent auto-manages
    // created_at/updated_at as real Carbon instances too — same UTC
    // serialization issue as email_verified_at/penalty_until below, so
    // this trait covers all four, not just the two explicit casts.
    use HasLocalJsonDates;

    // Notifiable removed: the app has its own Notification entity
    // (notifications table, see Notification.php) which it writes to
    // directly instead of going through Laravel's queued notification
    // channels — keeping both would mean two separate, disagreeing
    // notification systems.

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role',          // 'Resident' or 'Manager'
        'phone_number',
        'penalty_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'penalty_until' => 'datetime',
        ];
    }

    // tenant() and bookings() aren't in the Class Diagram's method list for
    // User, so they keep Laravel's usual bare-name convention rather than
    // a "get" prefix — only the four methods the diagram explicitly names
    // below are renamed/added to match it exactly.

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'user_id');
    }

    public function getBookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class, 'user_id');
    }

    /**
     * getApprovalLogs() per Class Diagram — was entirely missing before
     * (no relation existed between User and ApprovalLog at all).
     */
    public function getApprovalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'approver_id');
    }

    public function getNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    /**
     * hasRole(role:string):bool per Class Diagram — replaces the previous
     * isManager(), which only ever checked for one hardcoded role.
     * hasRole() is the general-purpose version WorkflowService needs
     * (checking against whatever role string a WorkflowTier requires,
     * not just 'Manager' specifically).
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
}