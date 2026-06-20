<?php

namespace App\Http\Resources;

use App\Services\ExamGradingService;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResultResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'course_id' => $this->course_id,
            'lessons_percentage' => $this->lessons_percentage,
            'exam_percentage' => $this->exam_percentage,
            'final_score' => $this->final_score,
            'weights' => [
                'lessons' => (int) (ExamGradingService::LESSONS_WEIGHT * 100),
                'exam' => (int) (ExamGradingService::EXAM_WEIGHT * 100),
            ],
            'passed' => (float) $this->final_score >= ExamGradingService::PASS_THRESHOLD,
            'eligible_for_certificate' => (float) $this->final_score >= ExamGradingService::CERTIFICATE_THRESHOLD,
        ];
    }
}
