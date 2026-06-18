<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\OperationalRuleController;
use App\Http\Controllers\WorkflowTierController;
use App\Http\Controllers\ApprovalLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Algorithm and Governance Routes (Testing)
Route::prefix('governance')->group(function () {
    // Read and Create Base Rules
    // Get rules for a specific facility
    // Admin creates new rule (max capacity/grace period/tiers)
    Route::get('/rules/{facility_id}', [OperationalRuleController::class, 'show']);
    Route::post('/rules', [OperationalRuleController::class, 'store']); 
});

Route::prefix('workflow')->group(function () {
    // 1. Runs the algorithm for a booking
    Route::post('/process', [WorkflowTierController::class, 'process']);

    //2. Admin Settings: CRUD for workflow tiers and conditions (e.g., capacity thresholds, time-based rules)
    Route::post('/', [WorkflowTierController::class, 'store']);
    Route::put('/{tier_id}', [WorkflowTierController::class, 'update']);
    Route::delete('/{tier_id}', [WorkflowTierController::class, 'destroy']);
});

// Audit trail: Admin retrieves approval logs for a specific request
Route::get('/approval-logs/{request_id}', [ApprovalLogController::class, 'index']);

// Lock these endpoints behind Sanctum to ensure only authenticated users can look up data
Route::middleware('auth:sanctum')->group(function () {
    
    // Route for submitting dynamic facility bookings (FR1, FR3)
    Route::post('/bookings', [BookingController::class, 'store']);
    
    // Route for updating check-in states via physical scanners (FR5)
    Route::post('/bookings/{id}/check-in', [BookingController::class, 'checkIn']);
    
});