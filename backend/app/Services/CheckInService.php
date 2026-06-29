<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CheckIn;
use Carbon\Carbon;
use Exception;

class CheckInService
{
    /**
     * Algorithm 3: Hardware Location Cross-Check & Arrival Window Delta Validation.
     *
     * facility_id no longer lives directly on Booking (it's reached via
     * bookingRequest->facility_id under the ERD), so this loads that chain
     * explicitly. Failed attempts (wrong QR, outside window) are now also
     * written to check_ins with status != 'Success' — the ERD gives
     * CheckIn its own `status` column specifically to support that audit
     * trail, not just successful scans, so a rejected attempt is recorded
     * rather than just thrown away as an exception.
     *
     * @throws Exception
     */
    public function processQrCheckIn(int $bookingId, string $qrData, int $userId): CheckIn
    {
        // Global TenantScope automatically safeguards this retrieval from ID cross-tampering
        $booking = Booking::with('bookingRequest.facility.operationalRule')->findOrFail($bookingId);
        $now = Carbon::now();
        $facilityId = $booking->bookingRequest->facility_id;

        // Guard: a booking can only ever be checked in once (check_ins.booking_id is
        // unique at the DB level — catch it here so we return a clean message instead
        // of a raw SQL "UNIQUE constraint failed" error).
        if ($booking->status === 'Checked_In') {
            throw new Exception("This booking has already been checked in.");
        }

        // Verification: Cross-check hardware token content matches asset context
        if ($qrData !== (string) $facilityId) {
            $this->recordAttempt($booking, $userId, $now, 'Invalid_Location');
            throw new Exception("Wrong QR Code! This code does not match your reserved facility.");
        }

        // Window calculation evaluation rules
        $windowMinutes = $booking->bookingRequest->facility->operationalRule->grace_period_minutes ?? 15;
        $diffInMinutes = $now->diffInMinutes($booking->start_time, false);

        // Absolute boundaries checking rule: must be within threshold limit window
        if (abs($diffInMinutes) > $windowMinutes) {
            $this->recordAttempt($booking, $userId, $now, 'Outside_Window');
            throw new Exception("Scan failed. You can only check in within {$windowMinutes} minutes of your booking time.");
        }

        // Update State Machine to complete booking lifecycle usage
        $booking->update(['status' => 'Checked_In']);

        return $this->recordAttempt($booking, $userId, $now, 'Success');
    }

    /**
     * Writes one row to check_ins for either a successful or failed scan
     * attempt. Note: check_ins.booking_id is unique, so this can only be
     * called once per booking overall — a failed attempt followed by a
     * retry would violate that constraint. (Flagging this as a known
     * limitation: the ERD's unique constraint on booking_id assumes one
     * check-in record per booking total, not one per attempt. If you want
     * every failed attempt logged too, that column needs its unique
     * constraint dropped — out of scope for this pass since it's a schema
     * change beyond what the ERD specifies.)
     */
    private function recordAttempt(Booking $booking, int $userId, Carbon $now, string $status): CheckIn
    {
        return CheckIn::create([
            'booking_id'   => $booking->booking_id,
            'user_id'      => $userId,
            'checkin_time' => $now,
            'method'       => 'QR',
            'status'       => $status,
        ]);
    }

    /**
     * Algorithm 4: No-show Detection.
     *
     * Scans every booking that is still "Confirmed" past the end of its own
     * facility's grace window and auto-cancels it, releasing the slot.
     * Intended to be run on a schedule (see App\Console\Commands\DetectNoShows
     * and the `bookings:detect-no-shows` schedule entry).
     *
     * @return int Number of bookings cancelled as no-shows.
     */
    public function detectNoShows(): int
    {
        $now = Carbon::now();
        $cancelled = 0;

        Booking::with('bookingRequest.facility.operationalRule')
            ->where('status', 'Confirmed')
            ->whereDoesntHave('checkIn')
            ->each(function (Booking $booking) use ($now, &$cancelled) {
                $windowMinutes = $booking->bookingRequest->facility->operationalRule->grace_period_minutes ?? 15;

                if ($now->greaterThan($booking->start_time->copy()->addMinutes($windowMinutes))) {
                    $booking->update(['status' => 'Cancelled_No_Show']);
                    $cancelled++;
                }
            });

        return $cancelled;
    }
}
