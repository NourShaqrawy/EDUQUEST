<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'description',
    'category_id',
    'publisher_id',
    'thumbnail',
    'status',
])]
class Course extends Model
{
    use HasFactory;

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }

    public function courseVideos()
    {
        return $this->hasMany(CourseVideo::class)->orderBy('order')->orderBy('id');
    }

    public function courseCertificates()
    {
        return $this->hasMany(CourseCertificate::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function exam()
    {
        return $this->hasOne(CourseExam::class);
    }

    public function results()
    {
        return $this->hasMany(CourseResult::class);
    }

    protected function getThumbnailAttribute($value)
    {
        if (strpos($value, 'http') === 0) {
            return $value;
        }

        return asset('storage/'.$value);
    }
}
