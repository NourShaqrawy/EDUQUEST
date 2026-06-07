<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'video_path',
        'video_144p',
        'video_360p',
        'video_720p',
        'duration',
        'order',
    ];

    protected $casts = [
        'duration' => 'integer',
        'order' => 'integer',
    ];

    // إضافة الروابط الكاملة (URLs) للردود (JSON Responses)
    protected $appends = [
        'video_url',
        'url_144p',
        'url_360p',
        'url_720p',
    ];

    // رابط الفيديو الأصلي
    public function getVideoUrlAttribute()
    {
        return $this->video_path ? asset('storage/'.$this->video_path) : null;
    }

    // روابط الجودات المختلفة
    public function getUrl144pAttribute()
    {
        return $this->video_144p ? asset('storage/'.$this->video_144p) : null;
    }

    public function getUrl360pAttribute()
    {
        return $this->video_360p ? asset('storage/'.$this->video_360p) : null;
    }

    public function getUrl720pAttribute()
    {
        return $this->video_720p ? asset('storage/'.$this->video_720p) : null;
    }

    // العلاقات
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function videoQuestions()
    {
        // المفتاح الأجنبي في جدول video_questions هو video_id وليس course_video_id الافتراضي
        return $this->hasMany(VideoQuestion::class, 'video_id');
    }

    /**
     * هل أكمل المستخدم هذا الفيديو؟
     * الإكمال = الإجابة على كل أسئلة الفيديو. الفيديو الذي لا يحوي أسئلة يُعتبر مكتملاً تلقائياً.
     */
    public function isCompletedBy(int $userId): bool
    {
        $questionIds = $this->videoQuestions()->pluck('id');

        if ($questionIds->isEmpty()) {
            return true;
        }

        $answeredCount = VideoQuestionAnswer::where('user_id', $userId)
            ->whereIn('question_id', $questionIds)
            ->count();

        return $answeredCount >= $questionIds->count();
    }

    /**
     * هل هذا الفيديو مفتوح للمستخدم؟
     * يُفتح فقط إذا أكمل المستخدم كل الفيديوهات السابقة في نفس الكورس (حسب الترتيب order ثم id).
     * الفيديو الأول دائماً مفتوح.
     */
    public function isUnlockedFor(int $userId): bool
    {
        $previousVideos = static::where('course_id', $this->course_id)
            ->where(function ($query) {
                $query->where('order', '<', $this->order)
                    ->orWhere(function ($tieBreaker) {
                        $tieBreaker->where('order', $this->order)
                            ->where('id', '<', $this->id);
                    });
            })
            ->get();

        foreach ($previousVideos as $previous) {
            if (! $previous->isCompletedBy($userId)) {
                return false;
            }
        }

        return true;
    }
}
