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
     * @param int $bookingId
     * @param string $qrData
     * @return CheckIn
     * @throws Exception
     */
    public function processQrCheckIn(int $bookingId, string $qrData): CheckIn
    {
        // Global TenantScope automatically safeguards this retrieval from ID cross-tampering
        $booking = Booking::with('facility.operationalRule')->findOrFail($bookingId);
        $now = Carbon::now();

        // Guard: a booking can only ever be checked in once (check_ins.booking_id is
        // unique at the DB level — catch it here so we return a clean message instead
        // of a raw SQL "UNIQUE constraint failed" error).
        if ($booking->status === 'Checked_In') {
            throw new Exception("This booking has already been checked in.");
        }

        // Verification: Cross-check hardware token content matches asset context
        if ($qrData !== (string)$booking->facility_id) {
            throw new Exception("Wrong QR Code! This code does not match your reserved facility.");
        }

        // Window calculation evaluation rules
        $windowMinutes = $booking->facility->operationalRule->grace_period_minutes ?? 15;
        $diffInMinutes = $now->diffInMinutes($booking->start_time, false);

        // Absolute boundaries checking rule: must be within threshold limit window
        if (abs($diffInMinutes) > $windowMinutes) {
            throw new Exception("Scan failed. You can only check in within {$windowMinutes} minutes of your booking time.");
        }

        // Update State Machine to complete booking lifecycle usage
        $booking->update(['status' => 'Checked_In']);

        return CheckIn::create([
            'booking_id' => $booking->id,
            'checkin_time' => $now
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

        Booking::with('facility.operationalRule')
            ->where('status', 'Confirmed')
            ->whereDoesntHave('checkIn')
            ->each(function (Booking $booking) use ($now, &$cancelled) {
                $windowMinutes = $booking->facility->operationalRule->grace_period_minutes ?? 15;

                if ($now->greaterThan($booking->start_time->copy()->addMinutes($windowMinutes))) {
                    $booking->update(['status' => 'Cancelled_No_Show']);
                    $cancelled++;
                }
            });

        return $cancelled;
    }
}