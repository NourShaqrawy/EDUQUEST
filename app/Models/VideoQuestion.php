<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;

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
        return $this->hasMany(VideoQuestionOption::class);
    }

    public function answers()
    {
        return $this->hasMany(VideoQuestionAnswer::class);
    }
}
