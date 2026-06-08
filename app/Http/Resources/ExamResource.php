<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'course_id'          => $this->course_id,
            'duration_minutes'   => $this->duration_minutes,
            'questions_to_serve' => $this->questions_to_serve,
            'is_published'       => $this->is_published,
            'bank_count'         => $this->whenCounted('questions'),
            'questions_count'    => $this->whenCounted('questions'), // backwards compat
            'questions'          => ExamQuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
