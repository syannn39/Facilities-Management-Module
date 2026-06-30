<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
        ]);

        $report = Report::create([
            'tenant_id'    => $request->user()->tenant_id,
            'generated_by' => $request->user()->id,
            'report_type'  => $validated['report_type'],
            'date_from'    => $validated['date_from'],
            'date_to'      => $validated['date_to'],
            'generated_at' => now(),
        ]);

        $fileUrl = $validated['format'] === 'pdf' ? $report->exportPDF() : $report->exportCSV();

        return response()->json([
            'success' => true,
            'message' => 'Report generated.',
            'data'    => $report->fresh(),
        ], 201);
    }

    /**
     * GET /api/reports/{id}/pdf  (auth:sanctum, Manager only)
     *
     * exportPDF() per Class Diagram.
     */
    public function exportPDF(int $id): JsonResponse
    {
        $report = Report::findOrFail($id);
        $fileUrl = $report->exportPDF();

        return response()->json([
            'success'  => true,
            'file_url' => $fileUrl,
        ]);
    }

    /**
     * GET /api/reports/{id}/csv  (auth:sanctum, Manager only)
     *
     * exportCSV() per Class Diagram.
     */
    public function exportCSV(int $id): JsonResponse
    {
        $report = Report::findOrFail($id);
        $fileUrl = $report->exportCSV();

        return response()->json([
            'success'  => true,
            'file_url' => $fileUrl,
        ]);
    }
}
