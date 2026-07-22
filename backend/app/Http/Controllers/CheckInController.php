<?php

namespace App\Http\Controllers;

use App\Services\CheckInService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * CheckInController — Class Diagram Figure 4.3.2.
 *
 * Moved here from BookingController::checkIn() (same logic, same
 * CheckInService::processQrCheckIn() call) — the diagram puts check-in
 * handling in its own controller rather than bundled into BookingController.
 */
class CheckInController extends Controller
{
    public function __construct(private CheckInService $checkInService) {}

    /**
     * POST /api/bookings/{id}/check-in  (auth:sanctum)
     *
     * store() per Class Diagram — FR5 Handler: validates a scanned QR
     * token against the booking and arrival window (Algorithm 3).
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'qr_data' => 'required|string',
            // Both optional: a device with no GPS / denied permission can
            // still check in via QR token + arrival window alone (see
            // CheckInService::processQrCheckIn — GPS check is skipped,
            // not required, when either is missing).
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $checkIn = $this->checkInService->processQrCheckIn(
                $id,
                $validated['qr_data'],
                $request->user()->id,
                $validated['lat'] ?? null,
                $validated['lng'] ?? null,
            );

            return response()->json([
                'success' => true,
                'message' => "Check-in Verified! Welcome to the facility.",
                'data'    => $checkIn,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/check-ins/{booking_id}  (auth:sanctum)
     *
     * show() per Class Diagram — retrieves the check-in record for a
     * given booking, if one exists (e.g. for a confirmation screen after
     * a successful scan).
     */
    public function show(int $booking_id): JsonResponse
    {
        $checkIn = \App\Models\CheckIn::where('booking_id', $booking_id)->first();

        if (!$checkIn) {
            return response()->json([
                'success' => false,
                'message' => 'No check-in record found for this booking.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $checkIn,
        ]);
    }
}