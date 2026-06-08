<?php

namespace App\Http\Requests\Publisher;

use Illuminate\Foundation\Http\FormRequest;

class UpsertCourseExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة عبر middleware role + فحص ملكية الكورس في المتحكم
    }

    public function rules(): array
    {
        return [
            'duration_minutes'   => 'required|integer|min:1|max:600',
            'questions_to_serve' => 'required|integer|min:10|max:35',
        ];
    }

    public function messages(): array
    {
        return [
            'duration_minutes.required'   => 'مدة الامتحان بالدقائق مطلوبة.',
            'duration_minutes.min'         => 'مدة الامتحان يجب أن تكون دقيقة واحدة على الأقل.',
            'questions_to_serve.required'  => 'عدد أسئلة الامتحان مطلوب.',
            'questions_to_serve.min'       => 'عدد أسئلة الامتحان يجب أن يكون 10 على الأقل.',
            'questions_to_serve.max'       => 'عدد أسئلة الامتحان لا يمكن أن يتجاوز 35.',
        ];
    }
}
