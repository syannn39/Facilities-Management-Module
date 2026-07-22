<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Availability extends Model
{
    protected $primaryKey = 'availability_id';
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null; 

    protected $fillable = [
        'facility_id',
        'date',
        'start_time',
        'end_time',
        'is_blocked',
    ];

    protected $casts = [
        'date' => 'date',
        'is_blocked' => 'boolean',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id', 'facility_id');
    }
}
