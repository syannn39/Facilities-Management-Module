<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Booking; 

/**
 * ReportController — Class Diagram Figure 4.3.2.
 */
class ReportController extends Controller
{
    public function __construct(private ReportingService $reportingService) {}

    /**
     * GET /api/reports  (auth:sanctum, Manager only)
     *
     * index() per Class Diagram — every report previously generated for
     * this tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $reports = Report::where('tenant_id', $request->user()->tenant_id)
            ->orderByDesc('generated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $reports,
        ]);
    }

    /**
     * POST /api/reports/generate  (auth:sanctum, Manager only)
     *
     * generate() per Class Diagram — compiles the metrics and writes a
     * Report row. format=csv|pdf decides which file is written
     * (see Report::exportPDF()/exportCSV()).
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => 'required|string',
            'date_from'   => 'required|date',
            'date_to'     => 'required|date|after_or_equal:date_from',
            'format'      => 'required|in:pdf,csv',
            'facility_id' => 'nullable|string',
        ]);

        $report = Report::create([
            'tenant_id'    => $request->user()->tenant_id,
            'generated_by' => $request->user()->id,
            'report_type'  => $validated['report_type'],
            'date_from'    => $validated['date_from'],
            'date_to'      => $validated['date_to'],
            'generated_at' => now(),
        ]);

        $facilityId = $validated['facility_id'] ?? 'all';

        // 3. Pass the filter down into your export functions!
        $fileUrl = $validated['format'] === 'pdf' 
            ? $report->exportPDF($facilityId) 
            : $report->exportCSV($facilityId);

        return response()->json([
            'success' => true,
            'message' => 'Report generated.',
            'file_url' => $fileUrl,
            'data'    => $report->fresh(),
        ], 201);
    }

    /**
     * GET /api/reports/{id}/pdf  (auth:sanctum, Manager only)
     */
    // FIX: Added Request $request to the parameters
    public function exportPDF(Request $request, int $id)
    {
        $report = Report::findOrFail($id);
        $facilityId = $request->query('facility_id', 'all');
        $fileUrl = $report->exportPDF($facilityId);

        return response()->download(Storage::disk('local')->path($fileUrl));
    }

    // FIX: Added Request $request to the parameters
    public function exportCSV(Request $request, int $id)
    {
        $report = Report::findOrFail($id);
        $facilityId = $request->query('facility_id', 'all');
        $fileUrl = $report->exportCSV($facilityId);

        return response()->download(Storage::disk('local')->path($fileUrl));
    }

    /**
     * GET /api/reports/dashboard-metrics (auth:sanctum, Manager only)
     * Fetches live data for the React charts and stats.
     */
    public function getDashboardMetrics(Request $request): JsonResponse
    {
        // 1. Read the incoming filters from React (with safe fallbacks)
        $dateFrom = $request->query('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());
        $facilityId = $request->query('facility_id', 'all');

        // 2. Filter Facility Usage (The Bar Chart)
        $facilitiesQuery = \App\Models\Facility::query();

        if ($facilityId !== 'all') {
            $facilitiesQuery->where('facility_id', $facilityId);
        }

        // Update this part to only return the result for the facility requested
        $facilities = $facilitiesQuery->withCount(['bookings' => function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('booking_date', [$dateFrom, $dateTo]);
        }])->get();

        // CRITICAL FIX: Ensure we are mapping the specific facility name from the query
        $chartData = $facilities->map(function ($facility) {
            return [
                'name' => $facility->name, // This should now correctly reflect the filtered facility
                'bookings' => $facility->bookings_count
            ];
        });

        // 3. Filter Overall Booking Stats (The KPI Cards)
        $requestsQuery = \App\Models\BookingRequest::whereBetween('booking_date', [$dateFrom, $dateTo]);
        
        if ($facilityId !== 'all') {
            $requestsQuery->where('facility_id', $facilityId);
        }

        $totalBookings = (clone $requestsQuery)->count();
        $failedBookings = (clone $requestsQuery)->whereIn('status', ['Rejected', 'Cancelled'])->count();
        $approvedBookings = (clone $requestsQuery)->where('status', 'Approved')->count();

        $cancellationRate = $totalBookings > 0 
            ? round(($failedBookings / $totalBookings) * 100, 1) 
            : 0;

        // Add this temporarily right before the return statement
        Log::info("Filtering for facility_id: " . $facilityId);
        Log::info("Facilities found count: " . $facilities->count());

        return response()->json([
            'success' => true,
            'data' => [
                'chartData' => $chartData,
                'stats' => [
                    'total_requests' => $totalBookings,
                    'approved' => $approvedBookings,
                    'rejected_cancelled' => $failedBookings,
                    'cancellation_rate' => $cancellationRate
                ]
            ]
        ]);
    }
}
