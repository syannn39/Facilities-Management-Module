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

        // Verification: Cross-check hardware token content matches asset context
        if ($qrData !== (string)$booking->facility_id) {
            throw new Exception("Wrong QR Code! This code does not match your reserved facility.");
        }

        // Window calculation evaluation rules
        $windowMinutes = $booking->facility->operationalRule->checkin_window_minutes ?? 15;
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
}