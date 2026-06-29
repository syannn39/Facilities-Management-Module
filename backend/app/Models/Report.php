<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $primaryKey = 'report_id';
    public $timestamps = false;
    const CREATED_AT = null;
    const UPDATED_AT = null; // ERD's only timestamp here is generated_at, handled manually

    protected $fillable = [
        'tenant_id',
        'generated_by',
        'report_type',
        'date_from',
        'date_to',
        'file_url',
        'generated_at',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'generated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
