<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'url_720p'
    ];

    // رابط الفيديو الأصلي
    public function getVideoUrlAttribute()
    {
        return $this->video_path ? asset('storage/' . $this->video_path) : null;
    }

    // روابط الجودات المختلفة
    public function getUrl144pAttribute()
    {
        return $this->video_144p ? asset('storage/' . $this->video_144p) : null;
    }

    public function getUrl360pAttribute()
    {
        return $this->video_360p ? asset('storage/' . $this->video_360p) : null;
    }

    public function getUrl720pAttribute()
    {
        return $this->video_720p ? asset('storage/' . $this->video_720p) : null;
    }

    // العلاقات
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function videoQuestions()
    {
        return $this->hasMany(VideoQuestion::class);
    }
}
