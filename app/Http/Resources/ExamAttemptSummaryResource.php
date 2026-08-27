<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ملخّص محاولة امتحان لعرضها في لوحة مراقبة الناشر/الأدمن.
 * يُرافقها عدّادان يُحسبان في الاستعلام: عدد كل الأحداث، وعدد الأحداث "المشبوهة".
 */
class ExamAttemptSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'status'           => $this->status,
            'score'            => $this->score,
            'started_at'       => optional($this->started_at)->toIso8601String(),
            'submitted_at'     => optional($this->submitted_at)->toIso8601String(),
            'events_count'     => (int) ($this->events_count ?? 0),
            'violations_count' => (int) ($this->violations_count ?? 0),
            'user'             => $this->whenLoaded('user', fn () => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),
        ];
    }
}
