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

    /**
     * generateReport() per Class Diagram — (re)compiles the metrics for
     * this report's tenant_id/date_from/date_to via ReportingService.
     * Delegates rather than reimplementing the queries here, same pattern
     * as OperationalRule's validate*() methods delegating to RuleEngineService.
     */
    public function generateReport(): array
    {
        return app(\App\Services\ReportingService::class)
            ->compileReport($this->tenant_id, $this->date_from->toDateString(), $this->date_to->toDateString());
    }

    /**
     * exportPDF() per Class Diagram — writes the file via ReportingService
     * and saves the resulting path to file_url on this row.
     */
    public function exportPDF(): string
    {
        $data = $this->generateReport();
        $fileUrl = app(\App\Services\ReportingService::class)->formatPDF($data, "report_{$this->report_id}");
        $this->update(['file_url' => $fileUrl]);

        return $fileUrl;
    }

    /**
     * exportCSV() per Class Diagram.
     */
    public function exportCSV(): string
    {
        $data = $this->generateReport();
        $fileUrl = app(\App\Services\ReportingService::class)->formatCSV($data, "report_{$this->report_id}");
        $this->update(['file_url' => $fileUrl]);

        return $fileUrl;
    }

    /**
     * getMetrics() per Class Diagram — same data as generateReport(), kept
     * as a separate method name since the diagram lists both; in practice
     * they do the same compilation (no separate "metrics-only, no file"
     * variant exists in ReportingService).
     */
    public function getMetrics(): array
    {
        return $this->generateReport();
    }
}
