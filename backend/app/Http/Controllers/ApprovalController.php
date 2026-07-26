<?php

namespace App\Http\Controllers;

use App\Models\BookingRequest;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\ApprovalLog;

/**
 * ApprovalController — Class Diagram Figure 4.3.2.
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

        // 1. STRICT TENANT ISOLATION
        $allPending = BookingRequest::with('facility.getOperationalRule', 'user')
            ->where('tenant_id', $manager->tenant_id)
            ->where('status', 'Pending')
            ->get();

        // 2. STRICT WORKFLOW ESCALATION (RBAC)
        // Filter out requests that are waiting for a different tier
        $pending = $allPending->filter(function (BookingRequest $bookingRequest) use ($manager) {
            $tier = $this->workflowService->getNextApprover($bookingRequest);
            return $tier && $manager->hasRole($tier->assigned_role);
        })->values();

        return response()->json([
            'success' => true,
            'data'    => $pending,
        ]);
    }

    /**
     * POST /api/approvals/{request_id}/approve  (auth:sanctum, Manager only)
     */
    public function approve(Request $request, int $request_id): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string',
        ]);

        $bookingRequest = BookingRequest::findOrFail($request_id);

        try {
            // Completely delegate to the WorkflowService. 
            // Do NOT manually insert the ApprovalLog here!
            $this->workflowService->processApproval($bookingRequest, $request->user(), $validated['remarks'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Booking request has been approved and escalated.',
                'data'    => $bookingRequest->fresh('getBooking'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * POST /api/approvals/{request_id}/reject  (auth:sanctum, Manager only)
     */
    public function reject(Request $request, int $request_id): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => 'required|string',
        ]);

        $bookingRequest = BookingRequest::findOrFail($request_id);

        try {
            // Completely delegate to the WorkflowService.
            $this->workflowService->processRejection($bookingRequest, $request->user(), $validated['remarks']);

            return response()->json([
                'success' => true,
                'message' => 'Booking request has been rejected. The slot is now available for other users.',
                'data'    => $bookingRequest->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}