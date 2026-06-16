<?php

namespace App\Http\Controllers;

use App\Services\SchedulingService;
use App\Services\CheckInService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class BookingController extends Controller
{
    protected SchedulingService $schedulingService;
    protected CheckInService $checkInService;

    /**
     * Dependency Injection: Bind our business engines natively into the controller.
     */
    public function __construct(SchedulingService $schedulingService, CheckInService $checkInService)
    {
        $this->schedulingService = $schedulingService;
        $this->checkInService = $checkInService;
    }

    /**
     * FR1 & FR3 Handler: Processes scheduling requests and saves reservations.
     */
    public function store(Request $request): JsonResponse
    {
        // Enforce basic request structural validation
        $validated = $request->validate([
            'facility_id'    => 'required|integer',
            'start_time'     => 'required|date|after:now',
            'end_time'       => 'required|date|after:start_time',
            'purpose_of_use' => 'nullable|string',
            'guest_count'    => 'nullable|integer'
        ]);

        try {
            // Retrieve authenticated user context ID safely from token lookup
            $userId = $request->user()->id;
            
            // Invoke Algorithm 1 & 2 via our service layer
            $booking = $this->schedulingService->validateAndCreateBooking($validated, $userId);

            $message = ($booking->status === 'Pending') 
                ? 'Your booking requires manager approval. You will be notified once reviewed.'
                : 'Facility booking submitted successfully!';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $booking
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * FR5 Handler: Handles entry validation checks from scanned physical QR tokens.
     */
    public function checkIn(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'qr_data' => 'required|string'
        ]);

        try {
            // Invoke Algorithm 3 via our check-in service layer
            $checkIn = $this->checkInService->processQrCheckIn($id, $request->qr_data);
            
            return response()->json([
                'success' => true,
                'message' => "Check-in Verified! Welcome to the facility.",
                'data'    => $checkIn
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}