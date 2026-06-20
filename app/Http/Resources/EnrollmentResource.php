<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'course_id'   => $this->course_id,
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
            'course'      => CourseSummaryResource::make($this->whenLoaded('course')),
            'progress'    => $this->when(
                ! is_null($this->resource->getAttribute('progress')),
                fn () => $this->resource->getAttribute('progress')
            ),
        ];
    }
}
