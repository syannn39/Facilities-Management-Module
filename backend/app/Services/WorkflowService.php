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
 * Enforces sequential state-machine logic for multi-tier approvals.
 * Dynamically routes requests through intermediate states (e.g., Pending -> 
 * Approved (Tier 1) -> Approved) based on the facility's operational rules.
 */

class WorkflowService
{
    public function __construct(
        private SchedulingService $schedulingService,
        private NotificationService $notificationService,
    ) {}

    public function routeToApprover(BookingRequest $request): bool
    {
        return $this->getNextApprover($request) !== null;
    }

    public function getNextApprover(BookingRequest $request): ?WorkflowTier
    {
        $rule = $request->facility->getOperationalRule;
        
        // 1. If no rule or Auto-Approve is set, no human approver is needed
        if (!$rule || $rule->approval_tier <= 0) {
            return null; 
        }

        // 2. Count how many approvals this request ALREADY has
        $currentApprovalCount = ApprovalLog::where('request_id', $request->request_id)
            ->where('action', 'Approved')
            ->count();

        // 3. The level we are looking for is the next sequential step
        $nextTierLevel = $currentApprovalCount + 1;

        // 4. If we've already met or exceeded the required tiers, workflow is complete
        if ($currentApprovalCount >= $rule->approval_tier) {
            return null;
        }

        // 5. Fetch the SPECIFIC tier level needed right now
        $tier = WorkflowTier::where('rule_id', $rule->rule_id)
            ->where('tier_level', $nextTierLevel)
            ->first();
        
        // 6. The Tenant-Agnostic Fallback
        if (!$tier) {
            $defaultTier = new WorkflowTier();
            // Fall back to a universal 'Manager' role instead of tenant-specific titles
            // This ensures a generic admin can catch misconfigured facilities
            $defaultTier->assigned_role = 'Manager'; 
            $defaultTier->tier_level = $nextTierLevel;
            return $defaultTier; 
        }
        
        return $tier;
    }

    /**
     * Ensure that if no tiers are strictly configured, a single approval is enough to fully approve.
     */
    public function isFullyApproved(BookingRequest $request): bool
    {
        $rule = $request->facility->getOperationalRule;
        
        // If no rule or approval_tier is 0, it doesn't need workflow approval
        if (!$rule || $rule->approval_tier <= 0) {
            return true;
        }

        $totalTiers = WorkflowTier::where('rule_id', $rule->rule_id)->count();
        
        // If workflow tiers were never seeded/created in the database, 
        // fall back to treating 1 approval log as fully approved!
        if ($totalTiers === 0) {
            return ApprovalLog::where('request_id', $request->request_id)
                ->where('action', 'Approved')
                ->exists();
        }

        $tiersApproved = ApprovalLog::where('request_id', $request->request_id)
            ->where('action', 'Approved')
            ->count();

        return $tiersApproved >= min($totalTiers, $rule->approval_tier);
    }

    public function processApproval(BookingRequest $request, User $approver, ?string $remarks = null): bool
    {
        $tier = $this->getNextApprover($request);

        if (!$tier) {
            throw new Exception('This request has no pending approval tier.');
        }

        // RBAC Authorization Check
        $isManager = ($approver->hasRole('Manager') && ($tier->assigned_role === 'Facility Manager' || $tier->assigned_role === 'Manager'));
        
        if (!$approver->hasRole($tier->assigned_role) && !$isManager) {
            throw new Exception("Only a user with the '{$tier->assigned_role}' role can approve this tier.");
        }

        // Log the approval action
        $alreadyLogged = ApprovalLog::where('request_id', $request->request_id)
            ->where('approver_id', $approver->id)
            ->where('action', 'Approved')
            ->exists();

        if (!$alreadyLogged) {
            // Pass the remarks through to the logAction
            ApprovalLog::logAction($request->request_id, $approver->id, $tier->tier_level, 'Approved', $remarks);
        }

        // DYNAMIC STATE MACHINE ESCALATION
        if ($this->isFullyApproved($request)) {
            // 1. Fully approved! Mark request status as Approved
            $request->update(['status' => 'Approved']);

            // 2. CREATE THE BOOKING ROW & SCHEDULE VIA SCHEDULING SERVICE
            $this->schedulingService->confirmBookingFromRequest($request, 'Request');
            
            // 3. Send notification
            $this->notificationService->sendApprovalNotification($request->fresh());
        } 
        
        // FIX: We completely remove the 'else' block!
        // If it is partially approved, we do nothing to the status. 
        // It safely remains 'Pending' in the database, and dynamic 
        // log-counting logic will automatically route it to Tier 2!

        return true;
    }

    public function processRejection(BookingRequest $request, User $approver, ?string $reason = null): bool
    {
        $tier = $this->getNextApprover($request);
        $tierLevel = $tier->tier_level ?? 1;

        $alreadyLogged = ApprovalLog::where('request_id', $request->request_id)
            ->where('approver_id', $approver->id)
            ->where('action', 'Rejected')
            ->exists();

        if (!$alreadyLogged) {
            ApprovalLog::logAction($request->request_id, $approver->id, $tierLevel, 'Rejected', $reason);
        }

        // Rejection instantly terminates the workflow regardless of current tier
        $request->update(['status' => 'Rejected']);

        $this->notificationService->sendRejectionNotification($request, $reason);

        return true;
    }
}