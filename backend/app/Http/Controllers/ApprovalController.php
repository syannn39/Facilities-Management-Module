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
 *
 * Named to match the diagram exactly (the older ApprovalLogController
 * file is left in place, untouched, as an empty stub — routes now point
 * here instead). approve()/reject() delegate to WorkflowService, which
 * is what actually knows how multi-tier routing and ApprovalLog entries
 * work; this controller's job is just request validation + auth context.
 */
class ApprovalController extends Controller
{
    public function __construct(private WorkflowService $workflowService) {}

    /**
     * GET /api/approvals/pending  (auth:sanctum, Manager only)
     *
     * getPendingRequests() per Class Diagram — every Pending request
     * whose next approval tier matches this manager's role, within their
     * own tenant (TenantScope on BookingRequest already restricts that).
     */
    public function getPendingRequests(Request $request): JsonResponse
    {
        $manager = $request->user();

        $allPending = BookingRequest::with('facility.getOperationalRule', 'user')
            ->where('status', 'Pending')
            ->get();

        // LOGGING: Let's see what the system thinks about each request
        foreach ($allPending as $req) {
            $tier = $this->workflowService->getNextApprover($req);
            $hasRole = $tier ? $manager->hasRole($tier->assigned_role) : false;
            Log::info("Request {$req->request_id} Check: Tier found? " . ($tier ? "Yes" : "No") . " | Role match? " . ($hasRole ? "Yes" : "No"));
        }

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
        $bookingRequest = BookingRequest::findOrFail($request_id);

        try {
            // 1. Identify the current workflow tier
            $tier = $this->workflowService->getNextApprover($bookingRequest);
            $tierLevel = $tier ? $tier->tier_level : 1;

            // 2. Update status
            $bookingRequest->update(['status' => 'Approved']);
            
            // 3. Explicitly write to the approval_logs table
            ApprovalLog::logAction(
                $bookingRequest->request_id,
                $request->user()->id,
                $tierLevel,
                'Approved'
            );
            
            $this->workflowService->processApproval($bookingRequest, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Booking request has been approved.',
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
            // 1. Identify the current workflow tier
            $tier = $this->workflowService->getNextApprover($bookingRequest);
            $tierLevel = $tier ? $tier->tier_level : 1;

            // 2. Explicitly write to the approval_logs table
            ApprovalLog::logAction(
                $bookingRequest->request_id,
                $request->user()->id,
                $tierLevel,
                'Rejected',
                $validated['remarks']
            );

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
