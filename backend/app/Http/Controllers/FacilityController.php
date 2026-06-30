<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Services\SchedulingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
        $facilities = Facility::with('getOperationalRule')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $facilities,
        ]);
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
            'name'     => 'required|string',
            'category' => 'nullable|string',
            'status'   => 'nullable|string',
            'image_url' => 'nullable|string',
        ]);

        $facility = new Facility($validated);
        $facility->tenant_id = $request->user()->tenant_id;
        $facility->save();

        return response()->json([
            'success' => true,
            'message' => 'Facility created successfully.',
            'data'    => $facility,
        ], 201);
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
            'name'      => 'sometimes|string',
            'category'  => 'sometimes|nullable|string',
            'image_url' => 'sometimes|nullable|string',
        ]);

        $facility->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Facility updated successfully.',
            'data'    => $facility,
        ]);
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
}
