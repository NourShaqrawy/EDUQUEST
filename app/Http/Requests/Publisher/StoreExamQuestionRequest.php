<?php

namespace App\Http\Requests\Publisher;

use App\Models\CourseExam;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => 'required|string|max:1000',
            'options' => 'required|array|min:'.CourseExam::MIN_OPTIONS.'|max:'.CourseExam::MAX_OPTIONS,
            'options.*.option_text' => 'required|string|max:500',
            'options.*.is_correct' => 'required|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $correctCount = collect($this->input('options', []))
                ->filter(fn ($option) => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))
                ->count();

            if ($correctCount !== 1) {
                $validator->errors()->add('options', 'يجب تحديد إجابة صحيحة واحدة فقط لكل سؤال.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'options.min' => 'يجب أن يحتوي السؤال على خيارين على الأقل.',
            'options.max' => 'لا يمكن أن يتجاوز السؤال أربعة خيارات.',
        ];
    }
}
