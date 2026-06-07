<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class SubmitExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * الإرسال النهائي دفعة واحدة. الإجابات اختيارية لأن الطالب قد يكون حفظها
     * تدريجياً (autosave) مسبقاً؛ ما يُرسَل هنا يُحدِّث/يُكمل المحفوظ.
     */
    public function rules(): array
    {
        return [
            'answers' => 'sometimes|array',
            'answers.*.exam_question_id' => 'required|integer|exists:exam_questions,id',
            'answers.*.exam_question_option_id' => 'required|integer|exists:exam_question_options,id',
        ];
    }
}
