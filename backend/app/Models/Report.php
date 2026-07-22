<?php

namespace App\Models;

use App\Traits\HasLocalJsonDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class Report extends Model
{
    use HasLocalJsonDates; // generated_at/date_from/date_to are real date(time) casts below

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
    public function exportPDF(string|int $facilityId = 'all')
    {
        $reportData = $this->gatherReportData($facilityId);
        $pdf = Pdf::loadView('reports.pdf', ['reportData' => $reportData]);
        $fileName = 'reports/pdf/report_' . $this->report_id . '.pdf';
        
        // FIX: Force Windows to build the directory first
        Storage::disk('local')->makeDirectory('reports/pdf');
        
        Storage::disk('local')->put($fileName, $pdf->output());

        return $fileName;
    }

    public function exportCSV(string|int $facilityId = 'all')
    {
        $reportData = $this->gatherReportData($facilityId);
        $fileName = 'reports/csv/report_' . $this->report_id . '.csv';
        
        // FIX: Force Windows to build the directory first
        Storage::disk('local')->makeDirectory('reports/csv');
        
        $csvContent = "Facility Name,Total Bookings\n";
        foreach ($reportData['booking_frequency'] as $row) {
            $csvContent .= "{$row['facility_name']},{$row['count']}\n";
        }
        
        $cancellationPercent = number_format($reportData['cancellation_rate'] * 100, 1);
        $csvContent .= "\nCancellation Rate,{$cancellationPercent}%\n";

        Storage::disk('local')->put($fileName, $csvContent);

        return $fileName;
    }

    /**
     * The Shared Logic: Fetches and filters the data for any export format
     */
    private function gatherReportData(string|int $facilityId)
    {
        // 1. Filter Facility Usage
        $facilitiesQuery = \App\Models\Facility::query();

        if ($facilityId !== 'all') {
            $facilitiesQuery->where('facility_id', $facilityId);
        }

        $facilities = $facilitiesQuery->withCount(['bookings' => function ($query) {
            // Use the dates saved on this report instance
            $query->whereBetween('booking_date', [$this->date_from, $this->date_to]);
        }])->get();

        $bookingFrequency = $facilities->map(function($facility) {
            return [
                'facility_name' => $facility->name,
                'count'         => $facility->bookings_count
            ];
        })->toArray();

        // 2. Filter Overall Stats (Cancellation Rate)
        $requestsQuery = \App\Models\BookingRequest::whereBetween('booking_date', [$this->date_from, $this->date_to]);
        
        if ($facilityId !== 'all') {
            $requestsQuery->where('facility_id', $facilityId);
        }

        $totalBookings = (clone $requestsQuery)->count();
        $failedBookings = (clone $requestsQuery)->whereIn('status', ['Rejected', 'Cancelled'])->count();

        $cancellationRate = $totalBookings > 0 
            ? ($failedBookings / $totalBookings) 
            : 0;

        return [
            'date_from'                 => $this->date_from,
            'date_to'                   => $this->date_to,
            'booking_frequency'         => $bookingFrequency,
            'cancellation_rate'         => $cancellationRate,
            'approval_turnaround_hours' => 24 // Replace with your actual turnaround logic if needed
        ];
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