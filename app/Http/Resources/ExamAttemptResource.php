<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'remaining_seconds' => $this->remainingSeconds(),
            'score' => $this->score,
            // قناة WebSocket لمتابعة العدّ التنازلي لهذه المحاولة.
            'timer_channel' => 'exam-attempt.'.$this->id,
        ];
    }
}
