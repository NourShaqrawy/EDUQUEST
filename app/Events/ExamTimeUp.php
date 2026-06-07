<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * انتهاء وقت الامتحان واعتماد الإجابات المحفوظة تلقائياً.
 */
class ExamTimeUp implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $attemptId,
        public string $status,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('exam-attempt.'.$this->attemptId)];
    }

    public function broadcastAs(): string
    {
        return 'time.up';
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->status,
            'message' => 'انتهى وقت الامتحان واعتُمدت إجاباتك الحالية.',
        ];
    }
}
