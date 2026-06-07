<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotification implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $notification;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    public function broadcastOn()
    {
        return new Channel('user.'.$this->notification->user_id);
    }

    // اسم الحدث الذي يستمع له العميل (Echo .listen('.notification.new'))
    public function broadcastAs(): string
    {
        return 'notification.new';
    }

    // البيانات التي تُرسَل عبر WebSocket
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
            'data' => $this->notification->data,
            'read_at' => $this->notification->read_at,
            'created_at' => $this->notification->created_at,
        ];
    }
}
