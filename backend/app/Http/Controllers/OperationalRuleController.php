<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
        $rule = \App\Models\OperationalRule::updateOrCreate(
            ['facility_id' => $validatedData['facility_id']], 
            $validatedData 
        );

        return response()->json([
            'message' => 'Operational rule saved successfully.',
            'data' => $rule
        ], 200);
    }
}
