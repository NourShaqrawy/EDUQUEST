<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class AutosaveExamAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_question_id' => 'required|integer|exists:exam_questions,id',
            'exam_question_option_id' => 'required|integer|exists:exam_question_options,id',
        ];
    }
}
