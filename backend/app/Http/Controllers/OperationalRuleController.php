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
        // 1. Keep your existing validation exactly as it is!
        $validatedData = $request->validate([
            'facility_id'          => 'required|integer',
            'max_capacity'         => 'required|integer|min:1',
            'approval_tier'        => 'required|integer|min:0',
            'grace_period_minutes' => 'required|integer|min:0',
        ]);

        // 2. Use updateOrCreate instead of create
        // Arg 1: The column to search for (Does this facility already have a rule?)
        // Arg 2: The data to update it with (or insert if it doesn't exist)
        $rule = \App\Models\OperationalRule::updateOrCreate(
            ['facility_id' => $validatedData['facility_id']], 
            $validatedData 
        );

        // 3. Return the response
        return response()->json([
            'message' => 'Operational rule saved successfully.',
            'data' => $rule
        ], 200);
    }
}
