<?php

namespace App\Services;

use App\Events\NewNotification;
use App\Models\Notification;

class NotificationService
{
    /**
     * إنشاء إشعار لمستخدم وبثّه لحظياً عبر Reverb على القناة user.{id}.
     * صفّ الإشعار يُكتب فوراً، أما البثّ فيمرّ عبر الطابور (يتطلّب queue:work + reverb:start).
     */
    public function send(
        int $userId,
        string $title,
        ?string $body = null,
        string $type = 'general',
        ?array $data = null,
    ): Notification {
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        broadcast(new NewNotification($notification));

        return $notification;
    }

    /** إرسال نفس الإشعار لعدّة مستخدمين (مثل إشعار كل المسجّلين في كورس). */
    public function sendToMany(
        iterable $userIds,
        string $title,
        ?string $body = null,
        string $type = 'general',
        ?array $data = null,
    ): void {
        foreach ($userIds as $userId) {
            $this->send((int) $userId, $title, $body, $type, $data);
        }
    }
}
