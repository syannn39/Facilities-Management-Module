<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\BookingRequest;
use Carbon\Carbon;
use Exception;

/**
 * RuleEngineService — Class Diagram Figure 4.3.3.
 *
 * Pulls the validation logic that was previously inline inside
 * SchedulingService::validateAndCreateBooking() out into its own class,
 * matching the Class Diagram's separation between "deciding whether a
 * request is even allowed" (this class) and "actually creating the
 * BookingRequest/Booking rows" (SchedulingService, which now calls this).
 *
 * validateCapacity() is genuinely new — capacity was never actually
 * checked before (a request for guest_count exceeding max_capacity would
 * previously have been accepted with no error).
 */
class RuleEngineService
{
    /**
     * evaluate(request) per Class Diagram — runs every rule check in one
     * pass and returns a structured result rather than throwing on the
     * first failure, so a caller (or a future form-validation UI) can see
     * every problem with one request at once rather than fixing them one
     * exception at a time.
     *
     * @param array $data Expects facility_id, start_time, end_time, and
     *                     optionally guest_count.
     * @return array{valid: bool, errors: array<string>, approval_tier: int}
     */
    public function evaluate(array $data): array
    {
        $facility = Facility::with('getOperationalRule')->findOrFail($data['facility_id']);
        $rule = $facility->getOperationalRule;

        if (!$rule) {
            return [
                'valid' => false,
                'errors' => ['Operational rules are not configured for this facility.'],
                'approval_tier' => 0,
            ];
        }

        $startTime = Carbon::parse($data['start_time']);
        $endTime = Carbon::parse($data['end_time']);
        $guestCount = isset($data['guest_count']) ? (int) $data['guest_count'] : 1;

        $errors = [];

        if (!$this->validateOperatingHours($rule, $startTime, $endTime)) {
            $errors[] = 'The selected time falls outside this facility\'s operating hours.';
        }

        if (!$this->validateAdvanceLimit($rule, $startTime)) {
            $errors[] = "Bookings can only be made up to {$rule->advance_booking_limit} days in advance.";
        }

        if (!$this->validateCapacity($rule, $guestCount)) {
            $errors[] = "Guest count exceeds this facility's maximum capacity of {$rule->max_capacity}.";
        }

        $existingTotalGuests = BookingRequest::where('facility_id', $facility->facility_id)
            ->whereIn('status', ['Pending', 'Approved'])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })
            ->sum('guest_count');

        if (($existingTotalGuests + $guestCount) > $rule->max_capacity) {
            $errors[] = "This facility is FULL for the selected time slot. Current capacity limit: {$rule->max_capacity}.";
        }

        return [
            'valid'         => empty($errors),
            'errors'        => $errors,
            'approval_tier' => $rule->approval_tier ?? 0,
        ];
    }

    public function validateCapacity(\App\Models\OperationalRule $rule, int $guestCount): bool
    {
        return $guestCount <= $rule->max_capacity;
    }

    public function validateOperatingHours(\App\Models\OperationalRule $rule, Carbon $startTime, Carbon $endTime): bool
    {
        $open = Carbon::parse($startTime->toDateString() . ' ' . $rule->opening_time);
        $close = Carbon::parse($startTime->toDateString() . ' ' . $rule->closing_time);

        return $startTime->gte($open) && $endTime->lte($close);
    }

    public function validateAdvanceLimit(\App\Models\OperationalRule $rule, Carbon $startTime): bool
    {
        $daysAhead = Carbon::now()->diffInDays($startTime, false);

        return $daysAhead <= $rule->advance_booking_limit;
    }

    /**
     * determineApprovalPath() per Class Diagram — returns 'Instant' if no
     * approval is needed, 'Request' if it must go through the workflow.
     */
    public function determineApprovalPath(Facility $facility): string
    {
        $tier = $facility->getOperationalRule->approval_tier ?? 0;

        return $tier > 0 ? 'Request' : 'Instant';
    }
}
