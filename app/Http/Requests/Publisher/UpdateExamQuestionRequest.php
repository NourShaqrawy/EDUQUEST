<?php

namespace App\Http\Requests\Publisher;

use App\Models\CourseExam;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => 'sometimes|required|string|max:1000',
            // عند إرسال الخيارات تُستبدل بالكامل، فتُطبّق نفس قيود الإنشاء.
            'options' => 'sometimes|array|min:'.CourseExam::MIN_OPTIONS.'|max:'.CourseExam::MAX_OPTIONS,
            'options.*.option_text' => 'required_with:options|string|max:500',
            'options.*.is_correct' => 'required_with:options|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('options')) {
                return;
            }

            $correctCount = collect($this->input('options', []))
                ->filter(fn ($option) => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))
                ->count();

            if ($correctCount !== 1) {
                $validator->errors()->add('options', 'يجب تحديد إجابة صحيحة واحدة فقط لكل سؤال.');
            }
        });
    }
}
