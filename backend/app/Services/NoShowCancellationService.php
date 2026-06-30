<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;

/**
 * NoShowCancellationService — Class Diagram Figure 4.3.3.
 *
 * Moved here from CheckInService::detectNoShows() (kept that method as a
 * thin delegating wrapper for backward compatibility — see CheckInService).
 * Algorithm 4 (No-show Detection) logic is unchanged; the new addition is
 * triggerNotification(), since the old version cancelled bookings silently
 * with no record of the resident ever being told why their slot disappeared.
 */
class NoShowCancellationService
{
    public function __construct(private NotificationService $notificationService) {}

    /**
     * handle() per Class Diagram — runs the full sweep: find every expired
     * Confirmed booking, cancel it, notify the resident. This is what
     * App\Console\Commands\DetectNoShows actually calls now.
     */
    public function handle(): int
    {
        $cancelled = 0;

        foreach ($this->getExpiredBookings() as $booking) {
            if ($this->cancelBooking($booking)) {
                $this->triggerNotification($booking);
                $cancelled++;
            }
        }

        return $cancelled;
    }

    /**
     * getExpiredBookings() per Class Diagram — every Confirmed booking
     * whose own facility's grace window has elapsed with no check-in.
     *
     * @return array<Booking>
     */
    public function getExpiredBookings(): array
    {
        $now = Carbon::now();

        return Booking::with('facility.getOperationalRule')
            ->where('status', 'Confirmed')
            ->whereDoesntHave('getCheckIn')
            ->get()
            ->filter(function (Booking $booking) use ($now) {
                $windowMinutes = $booking->facility->getOperationalRule->grace_period_minutes ?? 15;
                return $now->greaterThan($booking->start_time->copy()->addMinutes($windowMinutes));
            })
            ->all();
    }

    public function cancelBooking(Booking $booking): bool
    {
        return $booking->cancel();
    }

    public function triggerNotification(Booking $booking): bool
    {
        return $this->notificationService->sendNoShowNotification($booking);
    }
}
