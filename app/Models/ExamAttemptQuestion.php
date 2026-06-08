<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttemptQuestion extends Model
{
    public $timestamps = false;

    protected $fillable = ['exam_attempt_id', 'exam_question_id', 'display_order'];

    public function attempt()
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(ExamQuestion::class, 'exam_question_id');
    }
}
