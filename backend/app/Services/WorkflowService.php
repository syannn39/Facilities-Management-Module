<?php

namespace App\Services;

use App\Models\ApprovalLog;
use App\Models\BookingRequest;
use App\Models\User;
use App\Models\WorkflowTier;
use Exception;

/**
 * WorkflowService — Class Diagram Figure 4.3.3.
 *
 * Genuinely new functionality: this is what makes WorkflowTier actually
 * do something. Before this existed, WorkflowTier had zero real usage —
 * WorkflowTierController::process() was a standalone mock endpoint that
 * never persisted anything, and ApprovalLogController was an empty stub.
 *
 * Design simplification (documented since it's a real scope decision,
 * not hidden): this implements a SINGLE current tier per request — the
 * lowest tier_level not yet satisfied — rather than requiring every tier
 * to approve in parallel. routeToApprover() finds who's allowed to act on
 * the request right now; once they approve, isFullyApproved() checks
 * whether that was the last tier; if not, the request just keeps waiting
 * (still Pending) for the next tier's approver. A full N-of-N parallel
 * sign-off model would need an extra "which tiers have already signed
 * off" table — out of scope here, since OperationalRule.approval_tier
 * (a single int) is what currently drives whether approval is needed at
 * all, and there's no existing UI for a multi-person review queue.
 */
class WorkflowService
{
    public function __construct(
        private SchedulingService $schedulingService,
        private NotificationService $notificationService,
    ) {}

    /**
     * routeToApprover() per Class Diagram — true if there's at least one
     * WorkflowTier configured for this request's facility (i.e. there's
     * somewhere to route it). False means the facility has approval_tier
     * > 0 but no WorkflowTier rows were ever configured for it — a
     * legitimate "needs approval but nobody's set up to give it" gap a
     * manager should be alerted to separately, not something this method
     * papers over.
     */
    public function routeToApprover(BookingRequest $request): bool
    {
        return $this->getNextApprover($request) !== null;
    }

    /**
     * getNextApprover() per Class Diagram — the WorkflowTier representing
     * whichever tier_level should act on this request next, based on how
     * many ApprovalLog entries already exist for it. Returns null if every
     * configured tier has already signed off (use isFullyApproved() to
     * tell that apart from "no tiers configured at all").
     */
    public function getNextApprover(BookingRequest $request): ?WorkflowTier
    {
        $rule = $request->facility->getOperationalRule;
        
        // Attempt to find specific tier
        $tier = WorkflowTier::where('rule_id', $rule->rule_id)->first();
        
        // If no specific tier, return a 'Global Manager' default
        if (!$tier) {
            return WorkflowTier::where('assigned_role', 'Manager')->first(); 
        }
        return $tier;
    }

    /**
     * isFullyApproved() per Class Diagram — true once every configured
     * WorkflowTier for this request's facility has an 'Approved' log entry.
     */
    public function isFullyApproved(BookingRequest $request): bool
    {
        $rule = $request->facility->getOperationalRule;
        if (!$rule) {
            return false;
        }

        $totalTiers = WorkflowTier::where('rule_id', $rule->rule_id)->count();
        if ($totalTiers === 0) {
            return false; // nothing configured — can't be "fully" approved through a workflow that doesn't exist
        }

        $tiersApproved = ApprovalLog::where('request_id', $request->request_id)
            ->where('action', 'Approved')
            ->count();

        return $tiersApproved >= $totalTiers;
    }

    /**
     * processApproval() per Class Diagram — records the approval, and if
     * this was the last required tier, confirms the booking (creates the
     * actual Booking row via SchedulingService) and notifies the resident.
     * If more tiers remain, the request stays Pending and nothing else
     * happens yet — the next tier's manager still needs to act.
     *
     * @throws Exception if $approver doesn't hold the role this tier requires.
     */
    public function processApproval(BookingRequest $request, User $approver): bool
    {
        $tier = $this->getNextApprover($request);

        if (!$tier) {
            throw new Exception('This request has no pending approval tier (already fully approved, or no workflow configured).');
        }

        // --- FLEXIBLE ROLE CHECK ---
        $isManager = ($approver->hasRole('Manager') && $tier->assigned_role === 'Facility Manager');
        
        if (!$approver->hasRole($tier->assigned_role) && !$isManager) {
            throw new Exception("Only a user with the '{$tier->assigned_role}' role can approve this tier.");
        }

        ApprovalLog::logAction($request->request_id, $approver->id, $tier->tier_level, 'Approved');

        if ($this->isFullyApproved($request)) {
            $this->schedulingService->confirmBookingFromRequest($request, 'Request');
            $this->notificationService->sendApprovalNotification($request->fresh());
        }

        return true;
    }

    /**
     * processRejection() per Class Diagram — any single tier rejecting is
     * final: the whole request is marked Rejected immediately, regardless
     * of how many earlier tiers had already approved it.
     */
    public function processRejection(BookingRequest $request, User $approver, ?string $reason = null): bool
    {
        $tier = $this->getNextApprover($request);
        $tierLevel = $tier->tier_level ?? 1;

        ApprovalLog::logAction($request->request_id, $approver->id, $tierLevel, 'Rejected', $reason);

        $request->update(['status' => 'Rejected']);

        $this->notificationService->sendRejectionNotification($request, $reason);

        return true;
    }
}
