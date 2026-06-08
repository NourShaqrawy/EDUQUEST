<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ExamAttempt extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'course_exam_id',
        'started_at',
        'ends_at',
        'submitted_at',
        'status',
        'score',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'submitted_at' => 'datetime',
        'score' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(CourseExam::class, 'course_exam_id');
    }

    public function answers()
    {
        return $this->hasMany(ExamAttemptAnswer::class);
    }

    public function events()
    {
        return $this->hasMany(ExamAttemptEvent::class);
    }

    public function attemptQuestions()
    {
        return $this->hasMany(ExamAttemptQuestion::class)->orderBy('display_order');
    }

    /** هل ما زالت المحاولة قيد التنفيذ ولم تنتهِ مدتها؟ */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS && ! $this->hasTimeExpired();
    }

    /** هل انقضت المدة الزمنية (مرجعيّة الخادم)؟ */
    public function hasTimeExpired(): bool
    {
        return Carbon::now()->greaterThanOrEqualTo($this->ends_at);
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_EXPIRED], true);
    }

    /** الثواني المتبقية وفق ساعة الخادم (لا تقل عن صفر). */
    public function remainingSeconds(): int
    {
        // طوابع زمنية خام لتجنّب أي التباس في إشارة diffInSeconds بين إصدارات Carbon.
        return max(0, $this->ends_at->getTimestamp() - Carbon::now()->getTimestamp());
    }
}
