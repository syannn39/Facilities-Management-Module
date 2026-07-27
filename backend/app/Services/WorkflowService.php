<?php

namespace App\Services;

use App\Models\ApprovalLog;
use App\Models\BookingRequest;
use App\Models\User;
use App\Models\WorkflowTier;
use App\Models\Facility;
use App\Models\OperationalRule;
use Exception;

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

    /**
     * BULLETPROOF HELPER: Bypasses all Eloquent Relationship naming errors 
     * by querying the database directly using raw foreign keys.
     */
    private function getRuleSafely(BookingRequest $request): ?OperationalRule
    {
        // 1. Get the raw facility ID directly from the request column
        $facilityId = $request->facility_id;
        if (!$facilityId) {
            return null;
        }

        // 2. Query the rules table directly (completely bypassing the Facility model)
        return OperationalRule::where('facility_id', $facilityId)->first();
    }

    public function getNextApprover(BookingRequest $request): ?WorkflowTier
    {
        // Use the safe helper instead of fragile relationships
        $rule = $this->getRuleSafely($request);
        
        // 1. If no rule or Auto-Approve is set, no human approver is needed
        if (!$rule || $rule->approval_tier <= 0) {
            return null; 
        }

        // 2. Count how many approvals this request ALREADY has
        $requestId = $request->request_id ?? $request->id;
        $currentApprovalCount = ApprovalLog::where('request_id', $requestId)
            ->where('action', 'Approved')
            ->count();

        // 3. The level we are looking for is the next sequential step
        $nextTierLevel = $currentApprovalCount + 1;

        // 4. If we've already met or exceeded the required tiers, workflow is complete
        if ($currentApprovalCount >= $rule->approval_tier) {
            return null;
        }

        // 5. Safely get the primary key for the rule
        $ruleKey = $rule->rule_id ?? $rule->id;

        // 6. Fetch the SPECIFIC tier level needed right now
        $tier = WorkflowTier::where('rule_id', $ruleKey)
            ->where('tier_level', $nextTierLevel)
            ->first();
        
        // 7. The Tenant-Agnostic Fallback
        if (!$tier) {
            $defaultTier = new WorkflowTier();
            $defaultTier->assigned_role = 'Manager'; 
            $defaultTier->tier_level = $nextTierLevel;
            return $defaultTier; 
        }
        
        return $tier;
    }

    public function isFullyApproved(BookingRequest $request): bool
    {
        // Use the safe helper instead of fragile relationships
        $rule = $this->getRuleSafely($request);
        
        // If no rule or approval_tier is 0, it doesn't need workflow approval
        if (!$rule || $rule->approval_tier <= 0) {
            return true;
        }

        $ruleKey = $rule->rule_id ?? $rule->id;
        $totalTiers = WorkflowTier::where('rule_id', $ruleKey)->count();
        $requestId = $request->request_id ?? $request->id;
        
        // If workflow tiers were never seeded, fall back to treating 1 log as fully approved
        if ($totalTiers === 0) {
            return ApprovalLog::where('request_id', $requestId)
                ->where('action', 'Approved')
                ->exists();
        }

        $tiersApproved = ApprovalLog::where('request_id', $requestId)
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

        $approverRole = strtolower(trim($approver->role ?? ''));
        $requiredRole = strtolower(trim($tier->assigned_role));

        // RBAC Authorization Check (Case-insensitive bypass)
        $isManager = ($approverRole === 'manager' && in_array($requiredRole, ['facility manager', 'manager']));
        $hasMethodRole = method_exists($approver, 'hasRole') ? $approver->hasRole($tier->assigned_role) : false;
        
        if ($approverRole !== $requiredRole && !$isManager && !$hasMethodRole) {
            throw new Exception("Only a user with the '{$tier->assigned_role}' role can approve this tier.");
        }

        $requestId = $request->request_id ?? $request->id;

        // Log the approval action
        $alreadyLogged = ApprovalLog::where('request_id', $requestId)
            ->where('approver_id', $approver->id)
            ->where('action', 'Approved')
            ->exists();

        if (!$alreadyLogged) {
            ApprovalLog::logAction($requestId, $approver->id, $tier->tier_level, 'Approved', $remarks);
        }

        // DYNAMIC STATE MACHINE ESCALATION
        if ($this->isFullyApproved($request)) {
            // 1. Fully approved!
            $request->update(['status' => 'Approved']);
            $this->schedulingService->confirmBookingFromRequest($request, 'Request');
            $this->notificationService->sendApprovalNotification($request->fresh());
        } 
        
        return true;
    }

    public function processRejection(BookingRequest $request, User $approver, ?string $remarks = null): bool
    {
        $tier = $this->getNextApprover($request);
        $tierLevel = $tier->tier_level ?? 1;
        $requestId = $request->request_id ?? $request->id;

        $alreadyLogged = ApprovalLog::where('request_id', $requestId)
            ->where('approver_id', $approver->id)
            ->where('action', 'Rejected')
            ->exists();

        if (!$alreadyLogged) {
            ApprovalLog::logAction($requestId, $approver->id, $tierLevel, 'Rejected', $remarks);
        }

        $request->update(['status' => 'Rejected']);
        $this->notificationService->sendRejectionNotification($request, $remarks);

        return true;
    }
}