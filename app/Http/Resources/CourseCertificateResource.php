<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CourseCertificateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'certificate_code' => $this->certificate_code,
            'level'            => $this->level,
            'issued_at'        => $this->issued_at?->toIso8601String(),
            'student' => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ],
            'course' => [
                'id'    => $this->course->id,
                'title' => $this->course->title,
            ],
            'download_url' => url("/api/courses/{$this->course_id}/certificate/download"),
        ];
    }
}
