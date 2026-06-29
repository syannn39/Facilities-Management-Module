<?php

namespace App\Services;

use App\Models\Availability;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Facility;
use App\Models\Schedule;
use Carbon\Carbon;
use Exception;

class SchedulingService
{
    /**
     * Algorithm 1 & 2: Booking Validation & Overlap Conflict Detection.
     *
     * Under the ERD's two-table design, every attempt — instant or
     * approval-required — creates a BookingRequest first (this is the
     * literal request record). If the facility's approval_tier is 0, this
     * method immediately also creates the Booking row and backfills
     * BookingRequest.booking_id + sets status='Approved', so the caller
     * gets back something already confirmed. If approval_tier > 0, the
     * BookingRequest is left at status='Pending' with booking_id=null —
     * a Booking row only gets created later, by the (teammate-owned)
     * approval workflow once a manager approves it.
     *
     * @return array{request: BookingRequest, booking: ?Booking}
     * @throws Exception
     */
    public function validateAndCreateBooking(array $data, int $userId): array
    {
        $facility = Facility::with('operationalRule')->findOrFail($data['facility_id']);
        $startTime = Carbon::parse($data['start_time']);
        $endTime = Carbon::parse($data['end_time']);

        $this->assertNoConflict($facility->facility_id, $startTime, $endTime);

        $approvalTier = $facility->operationalRule->approval_tier ?? 0;

        $request = BookingRequest::create([
            'tenant_id'      => $facility->tenant_id,
            'facility_id'    => $facility->facility_id,
            'user_id'        => $userId,
            'booking_date'   => $startTime->toDateString(),
            'start_time'     => $startTime,
            'end_time'       => $endTime,
            'status'         => 'Pending', // flipped to 'Approved' below for instant bookings
            'purpose_of_use' => $data['purpose_of_use'] ?? null,
            'guest_count'    => $data['guest_count'] ?? 0,
        ]);

        if ($approvalTier > 0) {
            // Requires manager approval — stop here. The (teammate-owned)
            // ApprovalLogController/workflow is what eventually calls
            // confirmBookingFromRequest() below once approved.
            return ['request' => $request, 'booking' => null];
        }

        // Instant booking: approve immediately and create the Booking now.
        $booking = $this->confirmBookingFromRequest($request, 'Instant');

        return ['request' => $request, 'booking' => $booking];
    }

    /**
     * Turns an approved/instant BookingRequest into a real Booking row,
     * backfills the two-way link, and writes the historical Schedule
     * record. Public so the (teammate-owned) approval workflow can call
     * this too once a Pending request is approved — it shouldn't have to
     * duplicate this logic.
     */
    public function confirmBookingFromRequest(BookingRequest $request, string $bookingType): Booking
    {
        // tenant_id is set explicitly here rather than relying on
        // BelongsToTenant's creating hook (which reads Auth::user()) —
        // this method may end up being called from a context with no
        // authenticated request at all (e.g. if the teammate-owned
        // approval workflow ever runs this via a queued job rather than
        // inline in a controller), so it shouldn't depend on one existing.
        $booking = new Booking([
            'request_id'   => $request->request_id,
            'user_id'      => $request->user_id,
            'booking_type' => $bookingType, // 'Instant' | 'Request'
            'booking_date' => $request->booking_date,
            'start_time'   => $request->start_time,
            'end_time'     => $request->end_time,
            'status'       => 'Confirmed',
        ]);
        $booking->tenant_id = $request->tenant_id;
        $booking->save();

        $request->update([
            'booking_id' => $booking->booking_id,
            'status'     => 'Approved',
        ]);

        Schedule::create([
            'facility_id'   => $request->facility_id,
            'tenant_id'     => $request->tenant_id,
            'booking_id'    => $booking->booking_id,
            'slot_date'     => $request->booking_date,
            'start_time'    => $request->start_time->format('H:i:s'),
            'end_time'      => $request->end_time->format('H:i:s'),
            'is_available'  => false, // this slot is now taken
        ]);

        return $booking;
    }

    /**
     * Algorithm 2: Advanced Overlap Query Matrix.
     * Mathematical rule expression: (new_start < existing_end) AND (new_end > existing_start)
     *
     * Checks BookingRequest rows in either 'Pending' or 'Approved' state
     * (a Rejected request frees the slot back up immediately) — this is
     * the table facility_id now lives on, so the conflict lookup moved
     * here from Booking.
     *
     * @throws Exception
     */
    private function assertNoConflict(int $facilityId, Carbon $startTime, Carbon $endTime): void
    {
        $hasConflict = BookingRequest::where('facility_id', $facilityId)
            ->whereIn('status', ['Pending', 'Approved'])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })->exists();

        if ($hasConflict) {
            throw new Exception("This facility is no longer available. Please select another time.");
        }
    }

    /**
     * Builds the fixed list of slots between a facility's opening_time and
     * closing_time for the given date, and marks which ones are already
     * taken — powers the "Available Time Slots" list in the booking modal
     * (only slots with available=true are selectable on the frontend).
     *
     * A slot is unavailable if EITHER:
     *   - it overlaps an existing BookingRequest in Pending/Approved state
     *     (same rule as assertNoConflict above), OR
     *   - it overlaps an admin-set Availability block (is_blocked=true) —
     *     e.g. "Gym closed for maintenance 09:00-12:00"
     *
     * Slot length is a fixed 2 hours (matches the reference design's
     * screenshots — every facility shown there uses 2-hour blocks; this
     * isn't an admin-configurable field, per your call on that).
     *
     * @return array<int, array{start: string, end: string, available: bool}>
     */
    public function getAvailability(int $facilityId, string $date): array
    {
        $facility = Facility::with('operationalRule')->findOrFail($facilityId);
        $rule = $facility->operationalRule;

        $openTime  = $rule->opening_time ?? '08:00:00';
        $closeTime = $rule->closing_time ?? '22:00:00';
        $slotMinutes = 120;

        $dayStart = Carbon::parse("{$date} {$openTime}");
        $dayEnd   = Carbon::parse("{$date} {$closeTime}");

        // Existing requests for this facility on this date that actually
        // hold the slot (mirrors assertNoConflict's status set).
        $existingRequests = BookingRequest::where('facility_id', $facilityId)
            ->whereIn('status', ['Pending', 'Approved'])
            ->where('start_time', '<', $dayEnd)
            ->where('end_time', '>', $dayStart)
            ->get(['start_time', 'end_time']);

        // Admin-set blocks for this date (e.g. maintenance closures).
        $blocks = Availability::where('facility_id', $facilityId)
            ->where('date', $date)
            ->where('is_blocked', true)
            ->get(['start_time', 'end_time']);

        $slots = [];
        $slotStart = $dayStart->copy();

        while ($slotStart->copy()->addMinutes($slotMinutes)->lessThanOrEqualTo($dayEnd)) {
            $slotEnd = $slotStart->copy()->addMinutes($slotMinutes);

            $overlapsRequest = $existingRequests->contains(function ($req) use ($slotStart, $slotEnd) {
                return $slotStart->lessThan($req->end_time) && $slotEnd->greaterThan($req->start_time);
            });

            $overlapsBlock = $blocks->contains(function ($block) use ($date, $slotStart, $slotEnd) {
                $blockStart = Carbon::parse("{$date} {$block->start_time}");
                $blockEnd   = Carbon::parse("{$date} {$block->end_time}");
                return $slotStart->lessThan($blockEnd) && $slotEnd->greaterThan($blockStart);
            });

            $slots[] = [
                'start'     => $slotStart->format('H:i'),
                'end'       => $slotEnd->format('H:i'),
                'available' => !$overlapsRequest && !$overlapsBlock,
            ];

            $slotStart = $slotEnd;
        }

        return $slots;
    }
}
