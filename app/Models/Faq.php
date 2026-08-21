<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = [
        'question_en',
        'question_ar',
        'answer_en',
        'answer_ar',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    /**
     * السؤال مكتمل ثنائي اللغة (سؤال + إجابة بالعربية والإنكليزية) — شرط النشر.
     */
    public function isComplete(): bool
    {
        return filled($this->question_en)
            && filled($this->question_ar)
            && filled($this->answer_en)
            && filled($this->answer_ar);
    }

    public function suggestions()
    {
        return $this->hasMany(FaqSuggestion::class);
    }
}
