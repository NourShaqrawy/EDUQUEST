<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * نبضة عدّاد الامتحان المبثوثة عبر WebSocket على قناة المحاولة الخاصة.
 * phase: running (عدّاد دقائق) | final_minute (عدّاد ثوانٍ في الدقيقة الأخيرة).
 */
class ExamTimerTick implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $attemptId,
        public int $remainingSeconds,
        public string $phase,
        public string $unit,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('exam-attempt.'.$this->attemptId)];
    }

    public function broadcastAs(): string
    {
        return 'timer.tick';
    }

    public function broadcastWith(): array
    {
        return [
            'remaining_seconds' => $this->remainingSeconds,
            'phase' => $this->phase,
            'unit' => $this->unit,
        ];
    }
}
