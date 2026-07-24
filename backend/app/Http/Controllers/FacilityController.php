<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\Availability;
use App\Services\SchedulingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Exception;

class FacilityController extends Controller
{
    protected SchedulingService $schedulingService;

    public function __construct(SchedulingService $schedulingService)
    {
        $this->schedulingService = $schedulingService;
    }

    /**
     * GET /api/facilities  (auth:sanctum)
     *
     * Powers the "Browse Facilities" page (Figure 4.1.1). No explicit
     * tenant_id filter is written here on purpose — Facility::class uses
     * the BelongsToTenant trait, so the global TenantScope already injects
     * `WHERE tenant_id = <logged-in user's tenant_id>` on every query.
     * A School tenant's user only ever receives that school's facilities;
     * a Residential tenant's user only ever receives that property's
     * facilities — same endpoint, different data, by construction.
     */
    public function index(): JsonResponse
    {
        // eager-load the rules, AND count the related bookings/requests
        $facilities = Facility::with('getOperationalRule')
            ->withCount('bookings') // Creates a 'bookings_count' attribute
            ->withCount(['getBookingRequests as pending_requests_count' => function ($query) {
                // Filters the count to ONLY show pending requests
                $query->where('status', 'Pending'); 
            }])
            ->get();

        return response()->json(['success' => true, 'data' => $facilities]);
    }

    /**
     * GET /api/facilities/{id}  (auth:sanctum)
     *
     * show() per Class Diagram.
     */
    public function show(int $id): JsonResponse
    {
        $facility = Facility::with('getOperationalRule')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $facility,
        ]);
    }

    /**
     * POST /api/facilities  (auth:sanctum, Manager only)
     *
     * store() per Class Diagram — lets a manager add a new facility to
     * their own tenant. tenant_id is set explicitly from the
     * authenticated manager rather than accepted from the request body,
     * so a manager can never create a facility under a different tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'status' => 'required|string',
            'image_url' => 'nullable|string',
            
            // Allow initial governance settings to pass through safely
            'workflow_tier_id' => 'nullable|integer',
            'advance_booking_limit' => 'nullable|integer',
            // GPS check-in verification (optional — a facility left
            // unconfigured simply skips the distance check at check-in time)
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'checkin_radius_meters' => 'nullable|integer|min:1',
            'max_capacity' => 'nullable|integer', // Fixed naming
            'opening_time' => 'nullable|date_format:H:i:s',
            'closing_time' => 'nullable|date_format:H:i:s',
            'grace_period_minutes' => 'nullable|integer'
        ]);

        $facility = Facility::create($request->only([
            'name', 'category', 'status', 'image_url'
        ]));

        // Generate the Operational Rule for this facility using validated data
        $facility->getOperationalRule()->create([
            'advance_booking_limit' => $request->advance_booking_limit ?? 30,
            'max_capacity' => $request->max_capacity ?? 20, // Now matches database & frontend
            'approval_tier' => $request->workflow_tier_id ?? 0, 
            'opening_time' => $request->opening_time ?? '08:00:00', 
            'closing_time' => $request->closing_time ?? '22:00:00',
            'grace_period_minutes' => $request->grace_period_minutes ?? 15,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'checkin_radius_meters' => $request->checkin_radius_meters ?? 100,
        ]);

        return response()->json(['success' => true, 'data' => $facility->load('getOperationalRule')]);
    }

    /**
     * PUT /api/facilities/{id}  (auth:sanctum, Manager only)
     *
     * update() per Class Diagram.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $facility = Facility::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'status' => 'sometimes|string',
            'image_url' => 'nullable|string',
            'location' => 'nullable|string',
            
            // Sync all governance rule fields just in case they are passed here
            'workflow_tier_id' => 'nullable|integer',
            'advance_booking_limit' => 'nullable|integer',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'checkin_radius_meters' => 'nullable|integer|min:1',
            'max_capacity' => 'nullable|integer', 
            'opening_time' => 'nullable|date_format:H:i:s',
            'closing_time' => 'nullable|date_format:H:i:s',
            'grace_period_minutes' => 'nullable|integer',
        ]);

        $facility->update($request->only([
            'name', 'category', 'status', 'image_url', 'location', 'latitude', 'longitude'
        ]));

        $existingRule = $facility->getOperationalRule;

        $facility->getOperationalRule()->updateOrCreate(
            ['facility_id' => $facility->facility_id],
            [
                'advance_booking_limit' => $request->advance_booking_limit ?? ($existingRule->advance_booking_limit ?? 30),
                'max_capacity' => $request->max_capacity ?? ($existingRule->max_capacity ?? 20), // Fixed naming[cite: 1]
                'approval_tier' => $request->workflow_tier_id ?? ($existingRule->approval_tier ?? 0), 
                'opening_time' => $request->opening_time ?? ($existingRule->opening_time ?? '08:00:00'),
                'closing_time' => $request->closing_time ?? ($existingRule->closing_time ?? '22:00:00'),
                'grace_period_minutes' => $request->grace_period_minutes ?? ($existingRule->grace_period_minutes ?? 15),
    
                // Preserve existing GPS values if this request didn't send
                // new ones, instead of wiping them back to null every time
                // an unrelated field (like name) gets edited.
                'latitude' => $request->has('latitude') ? $request->latitude : ($existingRule->latitude ?? null),
                'longitude' => $request->has('longitude') ? $request->longitude : ($existingRule->longitude ?? null),
                'checkin_radius_meters' => $request->checkin_radius_meters ?? ($existingRule->checkin_radius_meters ?? 100),
            ]
        );

        return response()->json(['success' => true, 'data' => $facility->load('getOperationalRule')]);
    }

    /**
     * DELETE /api/facilities/{id}  (auth:sanctum, Manager only)
     *
     * destroy() per Class Diagram.
     */
    public function destroy(int $id): JsonResponse
    {
        $facility = Facility::findOrFail($id);
        $facility->delete();

        return response()->json([
            'success' => true,
            'message' => 'Facility deleted.',
        ]);
    }

    /**
     * PATCH /api/facilities/{id}/status  (auth:sanctum, Manager only)
     *
     * updateStatus() per Class Diagram — thin controller wrapper around
     * Facility::updateStatus() (the actual validation of which statuses
     * are legal lives on the model, see Facility.php).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:active,inactive,maintenance',
        ]);

        $facility = Facility::findOrFail($id);
        $facility->updateStatus($validated['status']);

        return response()->json([
            'success' => true,
            'message' => "Facility status updated to {$validated['status']}.",
            'data'    => $facility,
        ]);
    }

    /**
     * GET /api/facilities/{id}/availability?date=YYYY-MM-DD  (auth:sanctum)
     *
     * Returns the fixed list of 2-hour slots for that day, each flagged
     * available/unavailable, so the booking modal can grey out and disable
     * slots that are already taken — "only can select available slot".
     */
    public function availability(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        try {
            $slots = $this->schedulingService->getAvailability($id, $request->query('date'));

            return response()->json([
                'success' => true,
                'data'    => $slots,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function blockFacility(Request $request)
    {
        // 1. Validate the incoming request from React
        $validated = $request->validate([
            'facility_id' => 'required|exists:facilities,facility_id',
            'date'        => 'required|date',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
        ]);

        // 2. Create the maintenance block
        $block = Availability::create([
            'facility_id' => $validated['facility_id'],
            'date'        => $validated['date'],
            'start_time'  => $validated['start_time'],
            'end_time'    => $validated['end_time'],
            'is_blocked'  => true, // Hardcoded to true for maintenance
        ]);

        return response()->json([
            'message' => 'Facility blocked for maintenance successfully.',
            'data'    => $block
        ], 201);
    }

    /**
     * POST /api/facilities/{id}/qr-code  (auth:sanctum, Manager only)
     *
     * Generates the single check-in QR code for a facility. Each facility
     * gets exactly one token: if a token already exists, this call is a
     * no-op UNLESS `confirm=true` is passed, in which case the old token
     * is invalidated and a new one is issued (frontend is expected to show
     * a confirmation dialog before sending confirm=true — see AdminView.jsx).
     *
     * The token itself is what gets encoded into the QR image (rendered
     * client-side); it is what the tenant's scan hits at check-in time,
     * e.g. GET /checkin/{qr_code_token}.
     */
    public function generateQrCode(Request $request, int $id): JsonResponse
    {
        $facility = Facility::findOrFail($id);

        $alreadyExists = !empty($facility->qr_code_token);

        if ($alreadyExists && !$request->boolean('confirm')) {
            return response()->json([
                'success' => true,
                'requires_confirmation' => true,
                'message' => 'A QR code already exists for this facility. Resend with confirm=true to regenerate (this invalidates the old code).',
                'data' => [
                    'qr_code_token' => $facility->qr_code_token,
                    'qr_code_generated_at' => $facility->qr_code_generated_at,
                ],
            ]);
        }

        $facility->qr_code_token = (string) Str::uuid();
        $facility->qr_code_generated_at = now();
        $facility->save();

        return response()->json([
            'success' => true,
            'requires_confirmation' => false,
            'message' => $alreadyExists ? 'QR code regenerated. The old code no longer works.' : 'QR code generated.',
            'data' => [
                'qr_code_token' => $facility->qr_code_token,
                'qr_code_generated_at' => $facility->qr_code_generated_at,
            ],
        ]);
    }
}
