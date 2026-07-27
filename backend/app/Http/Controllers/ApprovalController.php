<?php

namespace App\Http\Controllers;

use App\Models\BookingRequest;
use App\Models\ApprovalLog;
use App\Models\WorkflowTier;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * ApprovalController — Bulletproof In-Memory Routing
 */
class ApprovalController extends Controller
{
    public function __construct(private WorkflowService $workflowService) {}

    /**
     * GET /api/approvals/pending  (auth:sanctum, Manager only)
     */
    public function getPendingRequests(Request $request): JsonResponse
    {
        $manager = $request->user();
        $managerRole = strtolower(trim($manager->role ?? ''));

        // 1. Fetch all pending status variations
        $allPending = BookingRequest::with(['user'])->whereIn('status', [
            'Pending', 'pending', 'Pending Approval', 'pending approval'
        ])->get();

        // 2. IN-MEMORY FILTERING
        $pending = $allPending->filter(function ($req) use ($manager, $managerRole) {
            
            // A. Safe Facility Fetch (Fallback to multiple naming conventions)
            $facility = $req->facility ?? $req->get_facility ?? $req->getFacility;
            if (!$facility) return false;

            // B. Loose Tenant Isolation Check (!= instead of !== prevents integer/string mismatch crashes)
            $facTenant = $facility->tenant_id ?? null;
            if ($facTenant && $facTenant != $manager->tenant_id) {
                return false;
            }

            // C. Safe Rule Fetch
            $rule = $facility->getOperationalRule ?? $facility->get_operational_rule ?? $facility->operationalRule;
            if (!$rule || ($rule->approval_tier ?? 0) <= 0) return false;

            // D. Calculate Escalation
            $requestId = $req->request_id ?? $req->id;
            $approvedCount = \App\Models\ApprovalLog::where('request_id', $requestId)
                ->where('action', 'Approved')
                ->count();

            if ($approvedCount >= $rule->approval_tier) return false;
            $nextTierLevel = $approvedCount + 1;

            // E. Safe Tier Fetch
            $ruleKey = $rule->rule_id ?? $rule->id;
            $tier = \App\Models\WorkflowTier::where('rule_id', $ruleKey)
                ->where('tier_level', $nextTierLevel)
                ->first();

            // F. Role Verification (Alias Fix applied here)
            $requiredRole = $tier ? strtolower(trim($tier->assigned_role)) : 'manager';
            
            // Treat "Property Manager" and "Manager" as exact aliases
            $normalizedManager = str_replace('property ', '', $managerRole);
            $normalizedRequired = str_replace('property ', '', $requiredRole);

            $hasDirectRole = ($normalizedManager === $normalizedRequired || $managerRole === $requiredRole);
            $hasMethodRole = method_exists($manager, 'hasRole') ? $manager->hasRole($tier->assigned_role ?? 'Manager') : false;

            return $hasDirectRole || $hasMethodRole;

        })->values();

        // 3. Ensure relations are loaded for the React UI to display names
        $transformed = $pending->map(function ($req) {
            $req->setRelation('facility', $req->facility ?? $req->get_facility ?? $req->getFacility);
            return $req;
        });

        return response()->json([
            'success' => true,
            'data'    => $transformed,
        ]);
    }
    
    /**
     * POST /api/approvals/{request_id}/approve
     */
    public function approve(Request $request, int $request_id): JsonResponse
    {
        $validated = $request->validate(['remarks' => 'nullable|string']);
        
        // Use a safe query instead of findOrFail to prevent primary key crashes
        $bookingRequest = BookingRequest::where('request_id', $request_id)->first();

        if (!$bookingRequest) {
            return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
        }

        try {
            $this->workflowService->processApproval($bookingRequest, $request->user(), $validated['remarks'] ?? null);
            return response()->json([
                'success' => true,
                'message' => 'Booking request has been approved and escalated.',
                'data'    => $bookingRequest->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/approvals/{request_id}/reject
     */
    public function reject(Request $request, int $request_id): JsonResponse
    {
        $validated = $request->validate(['remarks' => 'required|string']);

        $bookingRequest = BookingRequest::where('request_id', $request_id)->first();

        if (!$bookingRequest) {
            return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
        }

        try {
            $this->workflowService->processRejection($bookingRequest, $request->user(), $validated['remarks']);
            return response()->json([
                'success' => true,
                'message' => 'Booking request has been rejected.',
                'data'    => $bookingRequest->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}