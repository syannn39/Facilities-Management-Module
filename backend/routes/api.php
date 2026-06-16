<?php

use App\Http\Controllers\BookingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Lock these endpoints behind Sanctum to ensure only authenticated users can look up data
Route::middleware('auth:sanctum')->group(function () {
    
    // Route for submitting dynamic facility bookings (FR1, FR3)
    Route::post('/bookings', [BookingController::class, 'store']);
    
    // Route for updating check-in states via physical scanners (FR5)
    Route::post('/bookings/{id}/check-in', [BookingController::class, 'checkIn']);
    
});