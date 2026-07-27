<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OperationalRule;
use App\Models\WorkflowTier;
use App\Models\User;

class OperationalRuleController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Fully comprehensive validation array
        $validatedData = $request->validate([
            'facility_id'           => 'required|integer',
            'max_capacity'          => 'required|integer|min:1',
            'approval_tier'         => 'required|integer|min:0',
            'grace_period_minutes'  => 'required|integer|min:0',
            'advance_booking_limit' => 'required|integer|min:0',
            'opening_time'          => 'required|date_format:H:i:s',
            'closing_time'          => 'required|date_format:H:i:s',
            'latitude'              => 'nullable|numeric|between:-90,90',
            'longitude'             => 'nullable|numeric|between:-180,180',
            'checkin_radius_meters' => 'nullable|integer|min:0',
            'is_shared_facility'    => 'required|boolean',
            'concurrent_booking_limit' => 'required|integer|min:1',
        ]);

        // 2. updateOrCreate using the fully validated data
        $rule = OperationalRule::updateOrCreate(
            ['facility_id' => $validatedData['facility_id']], 
            $validatedData 
        );

        // --- NEW LOGIC: DYNAMIC WORKFLOW TIER SYNC ---
        
        // Use the primary key (fallback to 'id' if 'rule_id' isn't explicitly defined)
        $ruleKey = $rule->rule_id ?? $rule->id; 
        
        // First, wipe out any old, corrupted tiers for this rule
        WorkflowTier::where('rule_id', $ruleKey)->delete();

        // If the facility requires approval (Tier > 0), generate fresh, correct rows
        if ($validatedData['approval_tier'] > 0 && $request->user()) {
            
            $tenantId = $request->user()->tenant_id;

            // Fetch this specific tenant's roles (just like we did for the frontend dropdown)
            $rawRoles = User::where('tenant_id', $tenantId)
                ->whereNotIn('role', ['Resident', 'Student', 'Tenant', 'Admin', 'School Admin'])
                ->distinct()
                ->pluck('role')
                ->toArray();

            // Sort them to enforce hierarchy (Lecturer = Tier 1, HOD = Tier 2)
            usort($rawRoles, function ($a, $b) {
                $hierarchy = [
                    'Property Manager' => 1, 'JMB Member' => 2,
                    'Lecturer' => 1, 'Head of Department' => 2
                ];
                
                $weightA = $hierarchy[$a] ?? 99;
                $weightB = $hierarchy[$b] ?? 99;
                
                return $weightA <=> $weightB;
            });

            // Rebuild the tiers in the database up to the requested level
            for ($i = 1; $i <= $validatedData['approval_tier']; $i++) {
                // Grab the role matching the index, fallback to 'Manager' if they don't have enough roles
                $assignedRole = $rawRoles[$i - 1] ?? 'Manager';

                WorkflowTier::create([
                    'rule_id' => $ruleKey,
                    'tier_level' => $i,
                    'assigned_role' => $assignedRole
                ]);
            }
        }

        return response()->json([
            'message' => 'Operational rule and workflow tiers saved successfully.',
            'data' => $rule
        ], 200);
    }
}