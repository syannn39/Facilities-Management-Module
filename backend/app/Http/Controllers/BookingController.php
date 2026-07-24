<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingRequest;
use App\Services\SchedulingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class BookingController extends Controller
{
    protected SchedulingService $schedulingService;

    /**
     * Dependency Injection: Bind our business engines natively into the controller.
     */
    public function __construct(SchedulingService $schedulingService)
    {
        $this->schedulingService = $schedulingService;
    }

    /**
     * GET /api/bookings  (auth:sanctum)
     *
     * Powers the "My Bookings" page (Figure 4.1.6). Queries from
     * BookingRequest rather than Booking, because under the ERD's
     * two-table design a Pending or Rejected request never gets a Booking
     * row at all — querying Booking alone would silently hide those from
     * the user (they'd submit a request requiring approval and then never
     * see it show up anywhere). Each request here carries its linked
     * Booking (if one exists yet) via the `booking` relation.
     *
     * TenantScope already restricts this to the logged-in user's tenant;
     * the explicit user_id filter additionally ensures a resident doesn't
     * see other residents' requests within the same property.
     */
    public function index(Request $request): JsonResponse
    {
        $requests = BookingRequest::with(['facility', 'getBooking.getCheckIn', 'getApprovalLogs'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $requests,
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

            // Invoke Algorithm 1 & 2 via our service layer. Under the ERD's
            // two-table design this always produces a BookingRequest, and
            // a Booking too IF the facility allows instant booking.
            $result = $this->schedulingService->validateAndCreateBooking($validated, $userId);
            $bookingRequest = $result['request'];
            $booking = $result['booking'];

            $isPending = $booking === null;

            $message = $isPending
                ? 'Your booking requires manager approval. You will be notified once reviewed.'
                : 'Facility booking submitted successfully!';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => [
                    'request' => $bookingRequest,
                    'booking' => $booking, // null while Pending — nothing to check in to yet
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }


    public function cancel(int $bookingId, Request $request): JsonResponse
    {
        $booking = Booking::where('user_id', $request->user()->id)
            ->where('booking_id', $bookingId)
            ->firstOrFail();

        if ($booking->cancel(true)) {
            return response()->json(['success' => true, 'message' => 'Booking cancelled successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Cancellation failed.'], 400);
    }
}
