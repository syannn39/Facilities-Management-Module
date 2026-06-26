<?php

use App\Http\Controllers\AuthController;
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

// ── Public ──────────────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

// ── Authenticated ────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // FR1 & FR3: Submit a booking (instant or request-based)
    Route::post('/bookings', [BookingController::class, 'store']);

    // FR5: QR check-in
    Route::post('/bookings/{id}/check-in', [BookingController::class, 'checkIn']);

    // ── Governance / Testing ─────────────────────────────────────────────────
    // NOTE: moved inside auth:sanctum — these were previously open to anyone,
    // which contradicts the report's NFR (strict access control / tenant isolation).
    Route::prefix('governance')->group(function () {
        Route::get('/rules/{facility_id}', [OperationalRuleController::class, 'show']);
        Route::post('/rules',              [OperationalRuleController::class, 'store']);
    });

    Route::prefix('workflow')->group(function () {
        // NOTE: 'process' and 'store' were both bound to POST '/', so 'store'
        // could never be reached. 'process' is the one actually called from
        // the frontend, so it keeps the bare path; 'store' gets its own path.
        Route::post('/',            [WorkflowTierController::class, 'process']);
        Route::post('/rules',       [WorkflowTierController::class, 'store']);
        Route::put('/{tier_id}',    [WorkflowTierController::class, 'update']);
        Route::delete('/{tier_id}', [WorkflowTierController::class, 'destroy']);
    });

    Route::get('/approval-logs/{request_id}', [ApprovalLogController::class, 'index']);
});
