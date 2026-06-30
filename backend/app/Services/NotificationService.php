<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Notification;

/**
 * NotificationService — Class Diagram Figure 4.3.3.
 *
 * Writes real rows to the notifications table (previously this table
 * existed but nothing ever called Notification::create() — every
 * approval/rejection/cancellation/no-show happened silently with no
 * record of a message ever being sent to the user).
 */
class NotificationService
{
    public function sendApprovalNotification(BookingRequest $request): bool
    {
        return $this->write(
            $request,
            'Request_Approved',
            'Booking Approved',
            "Your booking request for {$this->facilityName($request)} has been approved and the facility has been reserved for you."
        );
    }

    public function sendRejectionNotification(BookingRequest $request, ?string $reason = null): bool
    {
        $body = "Your booking request for {$this->facilityName($request)} was declined.";
        if ($reason) {
            $body .= " Reason: {$reason}";
        }

        return $this->write($request, 'Request_Rejected', 'Booking Rejected', $body);
    }

    public function sendCancellationNotification(Booking $booking): bool
    {
        $request = $booking->bookingRequest;

        return $this->write(
            $request,
            'Booking_Cancelled',
            'Booking Cancelled',
            "Your booking for {$this->facilityName($request)} on {$booking->booking_date->format('Y-m-d')} has been cancelled."
        );
    }

    public function sendNoShowNotification(Booking $booking): bool
    {
        $request = $booking->bookingRequest;

        return $this->write(
            $request,
            'No_Show',
            'Booking Expired — No Show',
            "Your booking for {$this->facilityName($request)} was automatically cancelled because you didn't check in within the allowed window."
        );
    }

    private function write(BookingRequest $request, string $type, string $subject, string $body): bool
    {
        Notification::create([
            'tenant_id'    => $request->tenant_id,
            'user_id'      => $request->user_id,
            'request_id'   => $request->request_id,
            'type'         => $type,
            'subject'      => $subject,
            'message_body' => $body,
            'status'       => 'Unread',
            'sent_at'      => now(),
        ]);

        return true;
    }

    private function facilityName(BookingRequest $request): string
    {
        return $request->facility->name ?? 'the facility';
    }
}
