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

    /**
     * Builds the fixed list of 2-hour slots between a facility's open_time
     * and close_time for the given date, and marks which ones are already
     * taken — powers the "Available Time Slots" list in the booking modal
     * (only slots with available=true are selectable on the frontend).
     *
     * Uses the exact same "occupied" status set as Algorithm 2 above
     * (Confirmed, Pending, Checked_In) so a slot shown here as available
     * is guaranteed to pass validateAndCreateBooking()'s conflict check.
     *
     * @return array<int, array{start: string, end: string, available: bool}>
     */
    public function getAvailability(int $facilityId, string $date): array
    {
        $facility = Facility::with('operationalRule')->findOrFail($facilityId);
        $rule = $facility->operationalRule;

        $openTime  = $rule->open_time  ?? '08:00:00';
        $closeTime = $rule->close_time ?? '22:00:00';

        $dayStart = Carbon::parse("{$date} {$openTime}");
        $dayEnd   = Carbon::parse("{$date} {$closeTime}");

        // Existing bookings for this facility on this date that actually
        // hold the slot (mirrors the occupied-status set in Algorithm 2).
        $existingBookings = Booking::where('facility_id', $facilityId)
            ->whereIn('status', ['Confirmed', 'Pending', 'Checked_In'])
            ->where('start_time', '<', $dayEnd)
            ->where('end_time', '>', $dayStart)
            ->get(['start_time', 'end_time']);

        $slots = [];
        $slotStart = $dayStart->copy();

        while ($slotStart->copy()->addHours(2)->lessThanOrEqualTo($dayEnd)) {
            $slotEnd = $slotStart->copy()->addHours(2);

            $isTaken = $existingBookings->contains(function ($booking) use ($slotStart, $slotEnd) {
                // Same overlap rule as Algorithm 2: (new_start < existing_end) AND (new_end > existing_start)
                return $slotStart->lessThan($booking->end_time) && $slotEnd->greaterThan($booking->start_time);
            });

            $slots[] = [
                'start'     => $slotStart->format('H:i'),
                'end'       => $slotEnd->format('H:i'),
                'available' => !$isTaken,
            ];

            $slotStart = $slotEnd;
        }

        return $slots;
    }
}