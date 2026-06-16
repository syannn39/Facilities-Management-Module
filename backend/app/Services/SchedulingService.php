<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Facility;
use Carbon\Carbon;
use Exception;

class SchedulingService
{
    /**
     * Algorithm 1 & 2: Booking Validation & Overlap Conflict Detection.
     *
     * @param array $data
     * @param int $userId
     * @return Booking
     * @throws Exception
     */
    public function validateAndCreateBooking(array $data, int $userId): Booking
    {
        $facility = Facility::findOrFail($data['facility_id']);
        $startTime = Carbon::parse($data['start_time']);
        $endTime = Carbon::parse($data['end_time']);

        // Algorithm 2: Advanced Overlap Query Matrix
        // Mathematical rule expression: (new_start < existing_end) AND (new_end > existing_start)
        $hasConflict = Booking::where('facility_id', $facility->id)
            ->whereIn('status', ['Confirmed', 'Pending', 'Checked_In'])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })->exists();

        if ($hasConflict) {
            throw new Exception("This facility is no longer available. Please select another time.");
        }

        // State Machine Decision: Route based on configured facility tier
        // If approval_tier > 0, status stays 'Pending' for manual oversight
        $initialStatus = ($facility->approval_tier > 0) ? 'Pending' : 'Confirmed';

        return Booking::create([
            'user_id' => $userId,
            'facility_id' => $facility->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $initialStatus,
            'purpose_of_use' => $data['purpose_of_use'] ?? null,
            'guest_count' => $data['guest_count'] ?? 0,
        ]);
    }
}