<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Http\JsonResponse;

class FacilityController extends Controller
{
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
}
