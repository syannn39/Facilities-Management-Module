<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CheckIn;
use Carbon\Carbon;
use Exception;

class CheckInService
{
    public function __construct(private NoShowCancellationService $noShowCancellationService) {}

    /**
     * Algorithm 3: Hardware Location Cross-Check & Arrival Window Delta Validation.
     *
     * facility_id is now a direct column on Booking (Class Diagram), so
     * this no longer needs to go through bookingRequest to reach it.
     * Failed attempts (wrong QR, outside window) are also written to
     * check_ins with status != 'Success' for an audit trail of rejected
     * scans, not just successful ones.
     *
     * @throws Exception
     */
    public function processQrCheckIn(int $bookingId, string $qrData, int $userId): CheckIn
    {
        // Global TenantScope automatically safeguards this retrieval from ID cross-tampering
        $booking = Booking::with('facility.getOperationalRule')->findOrFail($bookingId);
        $now = Carbon::now();

        // Guard: a booking can only ever be checked in once (check_ins.booking_id is
        // unique at the DB level — catch it here so we return a clean message instead
        // of a raw SQL "UNIQUE constraint failed" error).
        if ($booking->status === 'Checked_In') {
            throw new Exception("This booking has already been checked in.");
        }

        // Verification: Cross-check scanned QR token against this facility's
        // current qr_code_token (NOT facility_id — facility_id never changes,
        // so checking against it would mean a photographed/leaked QR code
        // works forever regardless of how many times a manager regenerates
        // it from the Admin panel. qr_code_token is the rotating secret:
        // FacilityController::generateQrCode() issues a fresh one on
        // regeneration, which immediately invalidates every old printout).
        $currentToken = $booking->facility->qr_code_token;

        if (!$currentToken) {
            $this->recordAttempt($booking, $userId, $now, 'Invalid_Location');
            throw new Exception("This facility does not have an active QR code yet.");
        }

        if (!hash_equals($currentToken, $qrData)) {
            $this->recordAttempt($booking, $userId, $now, 'Invalid_Location');
            throw new Exception("Wrong QR Code! This code does not match your reserved facility.");
        }

        // Window calculation evaluation rules
        $windowMinutes = $booking->facility->getOperationalRule->grace_period_minutes ?? 15;
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
     * retry would violate that constraint. (Known limitation: if every
     * failed attempt needs its own row, that unique constraint needs to
     * be dropped — beyond what either diagram specifies, so left as-is.)
     */
    private function recordAttempt(Booking $booking, int $userId, Carbon $now, string $status): CheckIn
    {
        // Use updateOrCreate to handle existing scan attempts for this booking
        return CheckIn::updateOrCreate(
            ['booking_id' => $booking->booking_id],
            [
                'user_id'      => $userId,
                'checkin_time' => $now,
                'method'       => 'QR',
                'status'       => $status,
            ]
        );
    }

    /**
     * Algorithm 4: No-show Detection.
     *
     * Kept as a thin wrapper for backward compatibility (App\Console\Commands\DetectNoShows
     * already calls this method name) — the real logic now lives in
     * NoShowCancellationService::handle(), matching where the Class
     * Diagram's Service Layer puts it.
     *
     * @return int Number of bookings cancelled as no-shows.
     */
    public function detectNoShows(): int
    {
        return $this->noShowCancellationService->handle();
    }
}
