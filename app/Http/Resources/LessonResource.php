<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * عرض الدرس للطالب مع حالة الفتح/المشاهدة/الإكمال.
 * تُخفى روابط ومسارات الفيديو إذا كان الدرس غير متاح (حماية على الخادم).
 * يعتمد على حقول can_watch / is_watched / is_completed المضبوطة في المتحكم.
 */
class LessonResource extends JsonResource
{
    public function toArray($request): array
    {
        $canWatch = (bool) ($this->can_watch ?? false);

        $base = [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'order' => $this->order,
            'duration' => $this->duration,
            'can_watch' => $canWatch,
            'is_watched' => (bool) ($this->is_watched ?? false),
            'is_completed' => (bool) ($this->is_completed ?? false),
        ];

        // روابط الفيديو تظهر فقط للدرس المتاح؛ المقفل تُخفى مساراته وروابطه.
        if (! $canWatch) {
            return $base;
        }

        return $base + [
            'video_url' => $this->video_url,
            'url_144p' => $this->url_144p,
            'url_360p' => $this->url_360p,
            'url_720p' => $this->url_720p,
        ];
    }
}
