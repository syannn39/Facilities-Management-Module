<?php
namespace App\Http\Controllers;

use App\Models\BookingRequest;
use Illuminate\Http\Request;

class BookingRequestController extends Controller
{
    // Fetch all requests (with Facility and User data attached)
    public function index()
    {
        // Eager load the facility and the user who made the request
        $requests = BookingRequest::with(['facility', 'user'])->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $requests]);
    }

    // Update the status (Approve or Reject)
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:Approved,Rejected'
        ]);

        $bookingRequest = BookingRequest::findOrFail($id);
        $bookingRequest->update(['status' => $validated['status']]);

        // Note: If Approved, your system would ideally create a real 'Booking' record here!
        
        return response()->json(['success' => true, 'data' => $bookingRequest]);
    }
}