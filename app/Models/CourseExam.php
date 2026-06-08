<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseExam extends Model
{
    use HasFactory;

    /** الحدود المسموحة لعدد أسئلة الامتحان وعدد الخيارات لكل سؤال. */
    public const MIN_QUESTIONS = 10;

    public const MAX_QUESTIONS = 35;

    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 4;

    /** البنك يجب أن يحتوي على ضِعف عدد أسئلة الامتحان على الأقل. */
    public const BANK_MULTIPLIER = 2;

    protected $fillable = [
        'course_id',
        'duration_minutes',
        'questions_to_serve',
        'is_published',
    ];

    protected $casts = [
        'duration_minutes'   => 'integer',
        'questions_to_serve' => 'integer',
        'is_published'       => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function questions()
    {
        return $this->hasMany(ExamQuestion::class);
    }

    public function attempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
