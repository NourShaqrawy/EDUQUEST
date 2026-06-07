<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['video_id', 'question', 'time_in_video'])]
class VideoQuestion extends Model
{
    use HasFactory;

    public function video()
    {
        return $this->belongsTo(CourseVideo::class);
    }

    public function options()
    {
        // المفتاح الأجنبي هو question_id وليس video_question_id الافتراضي
        return $this->hasMany(VideoQuestionOption::class, 'question_id');
    }

    public function answers()
    {
        return $this->hasMany(VideoQuestionAnswer::class, 'question_id');
    }
}
