<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;

class WorkflowTierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * GET /api/workflow-tiers/roles
     * Fetch dynamic approval roles strictly scoped by tenant.
     */
   public function getTenantRoles(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        // Fetch distinct roles for this specific tenant
        $rawRoles = User::where('tenant_id', $tenantId)
            // Filter out end-users AND the Admin/Property Manager who is configuring the system
            ->whereNotIn('role', ['Resident', 'Student', 'Tenant', 'Property Manager', 'Admin', 'School Admin']) 
            ->distinct()
            ->pluck('role')
            ->toArray();

        // Custom sorting to ensure correct hierarchy (e.g., Lecturer before HOD)
        usort($rawRoles, function ($a, $b) {
            $hierarchy = [
                'Property Manager' => 1, 'JMB Member' => 2, // Residential
                'Lecturer' => 1, 'Head of Department' => 2  // Educational
            ];
            
            $weightA = $hierarchy[$a] ?? 99;
            $weightB = $hierarchy[$b] ?? 99;
            
            return $weightA <=> $weightB;
        });

        return response()->json([
            'success' => true,
            'data'    => $rawRoles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    // check rules and process booking request: auto-approve, reject, or route to approval tiers
    public function process(Request $request)
    {
        // 1. Validate the incoming booking request
        $validated = $request->validate([
            'facility_id' => 'required|integer',
            'requested_capacity' => 'required|integer|min:1',
            'user_role' => 'required|string'
        ]);

        // 2. Fetch the Operational Rule we just created in Postman!
        $rule = \App\Models\OperationalRule::where('facility_id', $validated['facility_id'])->first();

        if (!$rule) {
            return response()->json(['error' => 'No operational rules set for this facility.'], 404);
        }

        // 3. ALGORITHM STEP 1: Capacity Check
        if ($validated['requested_capacity'] > $rule->max_capacity) {
            return response()->json([
                'status' => 'Rejected',
                'reason' => 'Requested capacity exceeds facility limits.'
            ], 403); // 403 Forbidden
        }

        // 4. ALGORITHM STEP 2: Tier Routing
        if ($rule->approval_tier == 0) {
            return response()->json([
                'status' => 'Auto-Approved',
                'message' => 'Booking confirmed instantly. No manager approval required.'
            ], 200);
        } else {
            return response()->json([
                'status' => 'Pending Approval',
                'required_tiers' => $rule->approval_tier,
                'message' => "Routing request to Tier {$rule->approval_tier} management."
            ], 200);
        }
    }
}
