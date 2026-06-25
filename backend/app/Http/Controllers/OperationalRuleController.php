<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OperationalRuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validate the incoming request data
        $validatedData = $request->validate([
            'facility_id' => 'required|integer', // keep simple for testing 
            'max_capacity' => 'required|integer|min:1',
            'approval_tier' => 'required|integer|min:0',
            'grace_period_minutes' => 'required|integer|min:0',
        ]);

        // 2. Create a new OperationalRule in the database
        $rule = \App\Models\OperationalRule::create($validatedData);

        // 3. Return a response indicating success to frontend 
        return response()->json([
            'message' => 'Operational rule created successfully.',
            'data' => $rule
        ], 201);
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
}
