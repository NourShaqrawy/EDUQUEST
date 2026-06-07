<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_exam_id',
        'question',
    ];

    public function exam()
    {
        return $this->belongsTo(CourseExam::class, 'course_exam_id');
    }

    public function options()
    {
        return $this->hasMany(ExamQuestionOption::class);
    }

    public function correctOption()
    {
        return $this->hasOne(ExamQuestionOption::class)->where('is_correct', true);
    }
}
