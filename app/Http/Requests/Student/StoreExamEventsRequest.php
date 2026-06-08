<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الـ authz الفعلي: التحقق من المحاولة داخل الكنترولر
    }

    public function rules(): array
    {
        return [
            'events'             => 'required|array|max:100',
            'events.*.type'      => 'required|string|max:50',
            'events.*.meta'      => 'nullable|array',
            'events.*.client_at' => 'nullable|date',
        ];
    }
}
