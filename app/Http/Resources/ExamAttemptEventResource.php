<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * حدث مراقبة واحد ضمن الخط الزمني لمحاولة امتحان.
 * created_at = ساعة الخادم (المرجع الموثوق)، client_at = ساعة المتصفح (للاطلاع فقط).
 */
class ExamAttemptEventResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'meta'       => $this->meta,
            'client_at'  => optional($this->client_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
