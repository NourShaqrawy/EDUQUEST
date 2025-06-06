<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = ['course_id', 'title', 'description', 'video_url', 'video_order', 'duration'];
    
    public function course()
    {
        return $this->belongsTo(Course::class,'course_id');
    }

    public function exercises()
    {
        return $this->hasMany(Exercise::class,'video_id');
    }
    public function reactions()
    {
        return $this->hasMany(Video_Reactions::class,'video_id');
    }
}
