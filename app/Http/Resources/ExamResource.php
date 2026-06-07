<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'duration_minutes' => $this->duration_minutes,
            'is_published' => $this->is_published,
            'questions_count' => $this->whenCounted('questions'),
            'questions' => ExamQuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
