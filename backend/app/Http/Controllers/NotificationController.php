<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * NotificationController — Class Diagram Figure 4.3.2.
 */
class NotificationController extends Controller
{
    /**
     * GET /api/notifications  (auth:sanctum)
     *
     * index() per Class Diagram — the logged-in user's own notifications,
     * most recent first.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('sent_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $notifications,
        ]);
    }

    /**
     * PATCH /api/notifications/{id}/read  (auth:sanctum)
     *
     * markAsRead() per Class Diagram.
     */
    public function markAsRead(int $id): JsonResponse
    {
        $notification = Notification::findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'data'    => $notification,
        ]);
    }
}
