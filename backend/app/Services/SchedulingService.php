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
    public function __construct(private RuleEngineService $ruleEngineService) {}

    /**
     * Algorithm 1 & 2: Booking Validation & Overlap Conflict Detection.
     *
     * Now also runs RuleEngineService::evaluate() before anything else —
     * this is a real behavior change, not just a refactor: capacity,
     * operating-hours, and advance-booking-limit checks previously didn't
     * exist at all (a request for more guests than max_capacity, or
     * outside opening hours, would have been silently accepted before).
     *
     * Every attempt — instant or approval-required — creates a
     * BookingRequest first. If the facility's approval_tier is 0, this
     * method immediately also creates the Booking row and backfills
     * BookingRequest.booking_id + sets status='Approved'. If approval_tier
     * > 0, the BookingRequest is left at status='Pending' with
     * booking_id=null — a Booking row only gets created once
     * WorkflowService::processApproval() determines every required tier
     * has signed off.
     *
     * @return array{request: BookingRequest, booking: ?Booking}
     * @throws Exception
     */
    public function validateAndCreateBooking(array $data, int $userId): array
    {
        $facility = Facility::with('getOperationalRule')->findOrFail($data['facility_id']);
        $startTime = Carbon::parse($data['start_time']);
        $endTime = Carbon::parse($data['end_time']);

        $evaluation = $this->ruleEngineService->evaluate($data);
        if (!$evaluation['valid']) {
            throw new Exception(implode(' ', $evaluation['errors']));
        }

        $this->assertNoConflict($facility->facility_id, $startTime, $endTime);

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

        if ($evaluation['approval_tier'] > 0) {
            // Requires manager approval — stop here. WorkflowService is
            // what eventually calls confirmBookingFromRequest() below
            // once every required tier approves.
            return ['request' => $request, 'booking' => null];
        }

        // Instant booking: approve immediately and create the Booking now.
        $booking = $this->confirmBookingFromRequest($request, 'Instant');

        return ['request' => $request, 'booking' => $booking];
    }

    /**
     * Turns an approved/instant BookingRequest into a real Booking row,
     * backfills the two-way link, and writes the historical Schedule
     * record. Public so WorkflowService::processApproval() can call this
     * too once the last required tier approves — it shouldn't have to
     * duplicate this logic.
     */
    public function confirmBookingFromRequest(BookingRequest $request, string $bookingType): Booking
    {
        // tenant_id is set explicitly here rather than relying on
        // BelongsToTenant's creating hook (which reads Auth::user()) —
        // this method may be called from a context with no authenticated
        // request at all (e.g. WorkflowService running via a queued job),
        // so it shouldn't depend on one existing.
        $booking = new Booking([
            'request_id'   => $request->request_id,
            'facility_id'  => $request->facility_id,
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
     * (a Rejected request frees the slot back up immediately) — facility_id
     * lives on both BookingRequest and Booking now, but this checks
     * BookingRequest since that's the row that exists from the very first
     * moment a slot is claimed, before any Booking exists yet.
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
     * screenshots; not an admin-configurable field, per earlier decision).
     *
     * @return array<int, array{start: string, end: string, available: bool}>
     */
    public function getAvailability(int $facilityId, string $date): array
    {
        $facility = Facility::with('getOperationalRule')->findOrFail($facilityId);
        $rule = $facility->getOperationalRule;

        $openTime  = $rule->opening_time ?? '08:00:00';
        $closeTime = $rule->closing_time ?? '22:00:00';
        $slotMinutes = 120;

        $dayStart = Carbon::parse("{$date} {$openTime}");
        $dayEnd   = Carbon::parse("{$date} {$closeTime}");

        $existingRequests = BookingRequest::where('facility_id', $facilityId)
            ->whereIn('status', ['Pending', 'Approved'])
            ->where('start_time', '<', $dayEnd)
            ->where('end_time', '>', $dayStart)
            ->get(['start_time', 'end_time']);

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
