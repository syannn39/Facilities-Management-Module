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
        $facilities = Facility::with('operationalRule')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $facilities,
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
