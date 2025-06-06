<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{

    protected $fillable = ['category_id', 'publisher_id', 'title', 'description'];
    public function publisher()
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'course_id');
    }
    public function enrolledStudents()
    {
        return $this->belongsToMany(User::class, 'course_enrollments', 'course_id', 'student_id')->withTimestamps();
    }
}
