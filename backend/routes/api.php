<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\OperationalRuleController;
use App\Http\Controllers\WorkflowTierController;
use App\Http\Controllers\ApprovalLogController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
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

    // ── Facilities (Class Diagram Figure 4.3.2: index/store/show/update/destroy/updateStatus) ─
    Route::get('/facilities',              [FacilityController::class, 'index']);
    Route::post('/facilities',             [FacilityController::class, 'store']);
    Route::get('/facilities/{id}',         [FacilityController::class, 'show']);
    Route::put('/facilities/{id}',         [FacilityController::class, 'update']);
    Route::delete('/facilities/{id}',      [FacilityController::class, 'destroy']);
    Route::patch('/facilities/{id}/status',[FacilityController::class, 'updateStatus']);
    Route::post('/facilities/{id}/qr-code',[FacilityController::class, 'generateQrCode']);

    // Booking modal "Available Time Slots" list — only slots flagged
    // available=true are selectable on the frontend.
    Route::get('/facilities/{id}/availability', [FacilityController::class, 'availability']);

    // ── Bookings ──────────────────────────────────────────────────────────────
    // My Bookings page (Figure 4.1.6)
    Route::get('/bookings', [BookingController::class, 'index']);

    // FR1 & FR3: Submit a booking (instant or request-based)
    Route::post('/bookings', [BookingController::class, 'store']);

    // ── Check-In (Class Diagram Figure 4.3.2: CheckInController) ────────────────
    // NOTE: this used to point at BookingController::checkIn(), which was
    // removed when check-in logic moved into its own controller to match
    // the diagram — that old route would have 500'd on every check-in
    // attempt (calling a method that no longer exists). Fixed here.
    Route::post('/bookings/{id}/check-in', [CheckInController::class, 'store']);
    Route::get('/check-ins/{booking_id}',  [CheckInController::class, 'show']);

    // ── Approvals (Class Diagram Figure 4.3.2: ApprovalController) ──────────────
    Route::get('/approvals/pending',           [ApprovalController::class, 'getPendingRequests']);
    Route::post('/approvals/{request_id}/approve', [ApprovalController::class, 'approve']);
    Route::post('/approvals/{request_id}/reject',  [ApprovalController::class, 'reject']);

    // ── Availability blocking (Class Diagram Figure 4.3.2: AvailabilityController) ─
    Route::get('/availabilities',                [AvailabilityController::class, 'index']);
    Route::post('/availabilities',               [AvailabilityController::class, 'store']);
    Route::put('/availabilities/{id}',           [AvailabilityController::class, 'update']);
    Route::post('/availabilities/{id}/block',    [AvailabilityController::class, 'blockSlot']);
    Route::post('/availabilities/{id}/unblock',  [AvailabilityController::class, 'unblockSlot']);

    // ── Notifications (Class Diagram Figure 4.3.2: NotificationController) ──────
    Route::get('/notifications',           [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // ── Reports (Class Diagram Figure 4.3.2: ReportController) ──────────────────
    Route::get('/reports',              [ReportController::class, 'index']);
    Route::post('/reports/generate',    [ReportController::class, 'generate']);
    Route::get('/reports/{id}/pdf',     [ReportController::class, 'exportPDF']);
    Route::get('/reports/{id}/csv',     [ReportController::class, 'exportCSV']);

    // ── Governance / Testing (teammate's existing endpoints — untouched) ────────
    Route::prefix('governance')->group(function () {
        Route::get('/rules/{facility_id}', [OperationalRuleController::class, 'show']);
        Route::post('/rules',              [OperationalRuleController::class, 'store']);
    });

    Route::prefix('workflow')->group(function () {
        Route::post('/',            [WorkflowTierController::class, 'process']);
        Route::post('/rules',       [WorkflowTierController::class, 'store']);
        Route::put('/{tier_id}',    [WorkflowTierController::class, 'update']);
        Route::delete('/{tier_id}', [WorkflowTierController::class, 'destroy']);
    });

    // Old stub controller's route — left in place (ApprovalLogController
    // itself is still an empty stub, untouched) alongside the new
    // ApprovalController routes above, which are what's actually wired up
    // to WorkflowService now.
    Route::get('/approval-logs/{request_id}', [ApprovalLogController::class, 'index']);
    
});