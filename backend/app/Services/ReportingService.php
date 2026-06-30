<?php

namespace App\Services;

use App\Models\ApprovalLog;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Report;
use Carbon\Carbon;

/**
 * ReportingService — Class Diagram Figure 4.3.3.
 *
 * Genuinely new — previously the `reports` table existed (per the ERD)
 * with nothing reading or writing to it. This implements real aggregate
 * queries over Booking/BookingRequest/ApprovalLog.
 *
 * formatPDF()/formatCSV() write a file to storage/app/reports and return
 * its path as file_url — kept intentionally simple (plain CSV via
 * fputcsv, plain text "PDF" via a basic Dompdf-free text dump) rather
 * than pulling in a PDF library dependency that isn't already installed
 * in this project; swap formatPDF()'s body for a real PDF library call
 * if/when one is added.
 */
class ReportingService
{
    /**
     * getBookingFrequency() per Class Diagram — count of bookings per
     * facility within the given date range, for a given tenant.
     *
     * @return array<int, array{facility_id: int, facility_name: string, count: int}>
     */
    public function getBookingFrequency(int $tenantId, string $dateFrom, string $dateTo): array
    {
        return Booking::where('tenant_id', $tenantId)
            ->whereBetween('booking_date', [$dateFrom, $dateTo])
            ->with('facility')
            ->get()
            ->groupBy('facility_id')
            ->map(fn ($bookings, $facilityId) => [
                'facility_id'   => $facilityId,
                'facility_name' => $bookings->first()->facility->name ?? 'Unknown',
                'count'         => $bookings->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * getCancellationRate() per Class Diagram — proportion of bookings in
     * the range that ended up Cancelled_No_Show, as a 0.0-1.0 float.
     */
    public function getCancellationRate(int $tenantId, string $dateFrom, string $dateTo): float
    {
        $total = Booking::where('tenant_id', $tenantId)
            ->whereBetween('booking_date', [$dateFrom, $dateTo])
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $cancelled = Booking::where('tenant_id', $tenantId)
            ->whereBetween('booking_date', [$dateFrom, $dateTo])
            ->where('status', 'Cancelled_No_Show')
            ->count();

        return round($cancelled / $total, 4);
    }

    /**
     * getApprovalTurnaround() per Class Diagram — average time in hours
     * between a BookingRequest being created and its final ApprovalLog
     * decision, for requests that actually went through approval
     * (approval_tier > 0 requests only — instant bookings have no
     * ApprovalLog entries at all and would just be noise here).
     */
    public function getApprovalTurnaround(int $tenantId, string $dateFrom, string $dateTo): float
    {
        $requests = BookingRequest::where('tenant_id', $tenantId)
            ->whereBetween('booking_date', [$dateFrom, $dateTo])
            ->whereIn('status', ['Approved', 'Rejected'])
            ->get();

        $turnarounds = [];

        foreach ($requests as $request) {
            $lastLog = ApprovalLog::where('request_id', $request->request_id)
                ->orderByDesc('actioned_at')
                ->first();

            if ($lastLog) {
                $turnarounds[] = $request->created_at->diffInHours($lastLog->actioned_at);
            }
        }

        if (empty($turnarounds)) {
            return 0.0;
        }

        return round(array_sum($turnarounds) / count($turnarounds), 2);
    }

    /**
     * compileReport() per Class Diagram — bundles the three metrics above
     * into one array, for generateReport() (on the Report model) to hand
     * to formatPDF()/formatCSV().
     */
    public function compileReport(int $tenantId, string $dateFrom, string $dateTo): array
    {
        return [
            'date_from'        => $dateFrom,
            'date_to'          => $dateTo,
            'booking_frequency' => $this->getBookingFrequency($tenantId, $dateFrom, $dateTo),
            'cancellation_rate' => $this->getCancellationRate($tenantId, $dateFrom, $dateTo),
            'approval_turnaround_hours' => $this->getApprovalTurnaround($tenantId, $dateFrom, $dateTo),
        ];
    }

    public function formatCSV(array $reportData, string $filename): string
    {
        $path = storage_path("app/reports/{$filename}.csv");
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'w');
        fputcsv($handle, ['Facility', 'Booking Count']);
        foreach ($reportData['booking_frequency'] as $row) {
            fputcsv($handle, [$row['facility_name'], $row['count']]);
        }
        fputcsv($handle, []);
        fputcsv($handle, ['Cancellation Rate', $reportData['cancellation_rate']]);
        fputcsv($handle, ['Avg Approval Turnaround (hrs)', $reportData['approval_turnaround_hours']]);
        fclose($handle);

        return "reports/{$filename}.csv";
    }

    /**
     * Plain-text "PDF" stand-in — no PDF library is currently installed
     * in this project (composer.json wasn't part of what was shared), so
     * this writes a readable .txt summary rather than pulling in a new
     * dependency just for this one method. Swap the body for a real
     * library call (e.g. barryvdh/laravel-dompdf) once one's available.
     */
    public function formatPDF(array $reportData, string $filename): string
    {
        $path = storage_path("app/reports/{$filename}.txt");
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $lines = ["Facility Usage Report ({$reportData['date_from']} to {$reportData['date_to']})", ''];
        foreach ($reportData['booking_frequency'] as $row) {
            $lines[] = "{$row['facility_name']}: {$row['count']} bookings";
        }
        $lines[] = '';
        $lines[] = "Cancellation Rate: {$reportData['cancellation_rate']}";
        $lines[] = "Avg Approval Turnaround: {$reportData['approval_turnaround_hours']} hours";

        file_put_contents($path, implode("\n", $lines));

        return "reports/{$filename}.txt";
    }
}
