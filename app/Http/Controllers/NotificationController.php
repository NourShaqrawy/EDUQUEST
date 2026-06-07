<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * قائمة إشعارات المستخدم الحالي (مع فلترة الاختياري unread=1).
     */
    public function index(Request $request)
    {
        $query = $request->user()->notifications();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate(20);

        return response()->json([
            'status' => 'success',
            'unread_count' => $request->user()->notifications()->whereNull('read_at')->count(),
            'data' => $notifications,
        ]);
    }

    /**
     * عدد الإشعارات غير المقروءة فقط.
     */
    public function unreadCount(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'unread_count' => $request->user()->notifications()->whereNull('read_at')->count(),
        ]);
    }

    /**
     * إنشاء إشعار وإرساله إلى مستخدم (admin/publisher فقط) ثم بثّه عبر WebSocket.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'nullable|string|max:50',
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'data' => 'nullable|array',
        ]);

        $notification = $this->notifications->send(
            (int) $validated['user_id'],
            $validated['title'],
            $validated['body'] ?? null,
            $validated['type'] ?? 'general',
            $validated['data'] ?? null,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Notification sent',
            'data' => $notification,
        ], 201);
    }

    /**
     * تمييز إشعار واحد كمقروء (يخص صاحب الإشعار فقط).
     */
    public function markAsRead(Request $request, int $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read',
            'data' => $notification,
        ]);
    }

    /**
     * تمييز كل الإشعارات غير المقروءة كمقروءة.
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * حذف إشعار يخص المستخدم الحالي.
     */
    public function destroy(Request $request, int $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Notification deleted',
        ]);
    }
}
