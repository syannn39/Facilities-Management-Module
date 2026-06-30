<?php

namespace App\Services;

use App\Models\Booking;

/**
 * StateMachineService — Class Diagram Figure 4.3.3.
 *
 * Centralizes Booking's status transition rules. Before this existed,
 * every place that changed a booking's status did so with a raw
 * `$booking->update(['status' => '...'])` call scattered across
 * SchedulingService and CheckInService, with no single place enforcing
 * which transitions are actually valid. This doesn't change what those
 * services do internally (they already call this now, via
 * Booking::confirm()/cancel() or directly), but it does mean "is X -> Y
 * even a legal transition" is answered in exactly one place.
 */
class StateMachineService
{
    /**
     * Maps each status to the set of statuses it's allowed to transition
     * into. A booking is normally created directly as 'Confirmed' (not
     * transitioned into it from nothing), so 'Confirmed' appears as a
     * target from itself (re-confirm, a no-op but valid) and is the
     * starting point for the two ways a booking's lifecycle can end.
     */
    private array $validTransitions = [
        'Confirmed'         => ['Confirmed', 'Checked_In', 'Cancelled_No_Show'],
        'Checked_In'        => ['Checked_In'], // terminal — can't leave Checked_In
        'Cancelled_No_Show' => ['Cancelled_No_Show'], // terminal — can't leave Cancelled_No_Show
    ];

    /**
     * Attempts to move $booking to $newState. Returns false (does not
     * throw) if the transition isn't valid for its current state, so
     * callers like Booking::cancel() can surface a clean "couldn't do
     * that" result instead of an exception interrupting a routine UI flow.
     */
    public function transition(Booking $booking, string $newState): bool
    {
        if (!$this->isValidTransition($booking->status, $newState)) {
            return false;
        }

        $booking->update(['status' => $newState]);

        return true;
    }

    public function isValidTransition(string $fromState, string $toState): bool
    {
        return in_array($toState, $this->validTransitions[$fromState] ?? [], true);
    }

    public function getCurrentState(Booking $booking): string
    {
        return $booking->status;
    }

    public function getValidNextStates(Booking $booking): array
    {
        return $this->validTransitions[$booking->status] ?? [];
    }
}
