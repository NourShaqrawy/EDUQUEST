<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // ← مهم
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // علاقات
    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function videoQuestionAnswers()
    {
        return $this->hasMany(VideoQuestionAnswer::class);
    }

    public function courseCertificates()
    {
        return $this->hasMany(CourseCertificate::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function examAttempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function courseResults()
    {
        return $this->hasMany(CourseResult::class);
    }

    public function isEnrolledIn(int $courseId): bool
    {
        return $this->enrollments()->where('course_id', $courseId)->exists();
    }
}
