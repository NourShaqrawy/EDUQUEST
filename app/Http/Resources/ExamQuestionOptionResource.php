<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExamQuestionOptionResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $request->user();
        $isPrivileged = $user && in_array($user->role, ['publisher', 'admin'], true);

        return [
            'id' => $this->id,
            'option_text' => $this->option_text,
            // is_correct يُكشف للناشر/الأدمن فقط، ولا يظهر للطالب أثناء الامتحان.
            $this->mergeWhen($isPrivileged, [
                'is_correct' => $this->is_correct,
            ]),
        ];
    }
}
