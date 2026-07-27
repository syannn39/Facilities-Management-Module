<?php

namespace App\Http\Controllers;

use App\Models\Availability;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * AvailabilityController — Class Diagram Figure 4.3.2.
 *
 * Lets a manager block/unblock a facility's time slots directly (e.g.
 * "Gym closed for maintenance 09:00-12:00 on 2026-07-01"). Before this
 * existed, the `availabilities` table had no controller writing to it at
 * all — SchedulingService::getAvailability() already reads is_blocked
 * rows, but nothing ever created them through the API.
 */
class AvailabilityController extends Controller
{
    /**
     * GET /api/availabilities?facility_id=...  (auth:sanctum, Manager only)
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facility_id' => 'required|integer',
        ]);

        $blocks = Availability::where('facility_id', $validated['facility_id'])
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $blocks,
        ]);
    }

    /**
     * POST /api/availabilities  (auth:sanctum, Manager only)
     */
    public function store(Request $request): JsonResponse
    {
        // Security Gate: Flexible Role Alias Check
        $userRole = strtolower(trim($request->user()->role ?? ''));
        $authorizedRoles = [
            'admin', 'school admin', 'manager', 'property manager', 
            'facility manager', 'jmb member', 'lecturer', 'head of department'
        ];
        
        $isManagement = in_array($userRole, $authorizedRoles) 
                     || str_contains($userRole, 'manager') 
                     || str_contains($userRole, 'admin');

        // Fallback to strict method check just in case
        $hasMethodRole = method_exists($request->user(), 'hasRole') 
            ? ($request->user()->hasRole('Manager') || $request->user()->hasRole('Admin') || $request->user()->hasRole('Property Manager')) 
            : false;

        if (!$isManagement && !$hasMethodRole) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only management can modify availability.'
            ], 403);
        }

        // Chronological Time Validation added
        $validated = $request->validate([
            'facility_id' => 'required|integer',
            'date'        => 'required|date_format:Y-m-d',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i|after:start_time',
            'is_blocked'  => 'boolean',
        ]);

        $availability = Availability::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Availability block created.',
            'data'    => $availability,
        ], 201);
    }

    /**
     * PUT /api/availabilities/{id}  (auth:sanctum, Manager only)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Security Gate: Flexible Role Alias Check
        $userRole = strtolower(trim($request->user()->role ?? ''));
        $authorizedRoles = [
            'admin', 'school admin', 'manager', 'property manager', 
            'facility manager', 'jmb member', 'lecturer', 'head of department'
        ];
        
        $isManagement = in_array($userRole, $authorizedRoles) 
                     || str_contains($userRole, 'manager') 
                     || str_contains($userRole, 'admin');

        // Fallback to strict method check just in case
        $hasMethodRole = method_exists($request->user(), 'hasRole') 
            ? ($request->user()->hasRole('Manager') || $request->user()->hasRole('Admin') || $request->user()->hasRole('Property Manager')) 
            : false;

        if (!$isManagement && !$hasMethodRole) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only management can modify availability.'
            ], 403);
        }

        $availability = Availability::findOrFail($id);

        $validated = $request->validate([
            'date'       => 'sometimes|date_format:Y-m-d',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time'   => 'sometimes|date_format:H:i|after:start_time',
            'is_blocked' => 'sometimes|boolean',
        ]);

        $availability->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Availability block updated.',
            'data'    => $availability,
        ]);
    }

    /**
     * POST /api/availabilities/{id}/block  (auth:sanctum, Manager only)
     *
     * blockSlot() per Class Diagram — convenience shortcut over update()
     * for the common case of just flipping is_blocked on.
     */
    public function blockSlot(Request $request, int $id): JsonResponse
    {
        // Security Gate: Flexible Role Alias Check
        $userRole = strtolower(trim($request->user()->role ?? ''));
        $authorizedRoles = [
            'admin', 'school admin', 'manager', 'property manager', 
            'facility manager', 'jmb member', 'lecturer', 'head of department'
        ];
        
        $isManagement = in_array($userRole, $authorizedRoles) 
                     || str_contains($userRole, 'manager') 
                     || str_contains($userRole, 'admin');

        // Fallback to strict method check just in case
        $hasMethodRole = method_exists($request->user(), 'hasRole') 
            ? ($request->user()->hasRole('Manager') || $request->user()->hasRole('Admin') || $request->user()->hasRole('Property Manager')) 
            : false;

        if (!$isManagement && !$hasMethodRole) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only management can modify availability.'
            ], 403);
        }

        $availability = Availability::findOrFail($id);
        $availability->update(['is_blocked' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Slot blocked.',
            'data'    => $availability,
        ]);
    }

    /**
     * POST /api/availabilities/{id}/unblock  (auth:sanctum, Manager only)
     *
     * unblockSlot() per Class Diagram.
     */
    public function unblockSlot(Request $request, int $id): JsonResponse
    {
        // Security Gate: Flexible Role Alias Check
        $userRole = strtolower(trim($request->user()->role ?? ''));
        $authorizedRoles = [
            'admin', 'school admin', 'manager', 'property manager', 
            'facility manager', 'jmb member', 'lecturer', 'head of department'
        ];
        
        $isManagement = in_array($userRole, $authorizedRoles) 
                     || str_contains($userRole, 'manager') 
                     || str_contains($userRole, 'admin');

        // Fallback to strict method check just in case
        $hasMethodRole = method_exists($request->user(), 'hasRole') 
            ? ($request->user()->hasRole('Manager') || $request->user()->hasRole('Admin') || $request->user()->hasRole('Property Manager')) 
            : false;

        if (!$isManagement && !$hasMethodRole) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only management can modify availability.'
            ], 403);
        }

        $availability = Availability::findOrFail($id);
        $availability->update(['is_blocked' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Slot unblocked.',
            'data'    => $availability,
        ]);
    }
}