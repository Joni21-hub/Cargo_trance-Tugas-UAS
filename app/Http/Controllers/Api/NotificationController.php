<?php

namespace App\Http\Controllers\Api;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    /**
     * GET /api/v1/notifications
     * Return authenticated user's in-app notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = AppNotification::with('shipment:id,tracking_number,status')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(40)
            ->get();

        $unreadCount = AppNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return $this->sendResponse([
            'items' => $notifications,
            'unread_count' => $unreadCount,
        ], 'Notifications retrieved successfully');
    }

    /**
     * PATCH /api/v1/notifications/{id}/read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $notification = AppNotification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return $this->sendError('Notifikasi tidak ditemukan.', null, 404);
        }

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return $this->sendResponse($notification, 'Notifikasi ditandai sudah dibaca.');
    }

    /**
     * POST /api/v1/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        AppNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return $this->sendResponse(null, 'Semua notifikasi ditandai sudah dibaca.');
    }
}
