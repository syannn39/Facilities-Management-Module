<?php

namespace App\Http\Controllers;

use App\Services\SchedulingService;
use App\Services\CheckInService;
use App\Models\Booking;
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
     * GET /api/bookings  (auth:sanctum)
     *
     * Powers the "My Bookings" page (Figure 4.1.6). Only the logged-in
     * user's own bookings — TenantScope already restricts this to their
     * tenant, and we additionally filter by user_id since a resident
     * shouldn't see other residents' bookings within the same property.
     */
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::with('facility')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $bookings,
        ]);
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
            $booking->load('facility');

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