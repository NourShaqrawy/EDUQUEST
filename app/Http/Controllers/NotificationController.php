<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * قائمة إشعارات المستخدم الحالي (مع فلترة الاختياري unread=1).
     * الشكل: { status, message, data: { notifications: paginator, unread_count } }
     */
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = $user->notifications();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->latest()->paginate(20);
        $unreadCount   = $user->notifications()->whereNull('read_at')->count();

        return $this->success([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    /**
     * عدد الإشعارات غير المقروءة فقط.
     */
    public function unreadCount(Request $request)
    {
        return $this->success([
            'unread_count' => $request->user()->notifications()->whereNull('read_at')->count(),
        ]);
    }

    /**
     * إنشاء إشعار وإرساله إلى مستخدم (admin/publisher فقط) ثم بثّه عبر WebSocket.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'  => 'required|exists:users,id',
            'type'     => 'nullable|string|max:50',
            'title'    => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'body'     => 'nullable|string',
            'body_en'  => 'nullable|string',
            'body_ar'  => 'nullable|string',
            'data'     => 'nullable|array',
        ]);

        // دمج الحقول الثنائية داخل data حتى تُحفظ في الحقل JSON
        $data = $validated['data'] ?? [];
        foreach (['title_en', 'title_ar', 'body_en', 'body_ar'] as $key) {
            if (! empty($validated[$key])) {
                $data[$key] = $validated[$key];
            }
        }

        $notification = $this->notifications->send(
            (int) $validated['user_id'],
            $validated['title'],
            $validated['body'] ?? null,
            $validated['type'] ?? 'general',
            $data ?: null,
        );

        return $this->success($notification, 'تم إرسال الإشعار.', 201);
    }

    /**
     * تمييز إشعار واحد كمقروء (يخص صاحب الإشعار فقط).
     */
    public function markAsRead(Request $request, int $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->success($notification, 'تم تمييز الإشعار كمقروء.');
    }

    /**
     * تمييز كل الإشعارات غير المقروءة كمقروءة.
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->message('تم تمييز جميع الإشعارات كمقروءة.');
    }

    /**
     * حذف إشعار يخص المستخدم الحالي.
     */
    public function destroy(Request $request, int $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return $this->message('تم حذف الإشعار.');
    }
}
