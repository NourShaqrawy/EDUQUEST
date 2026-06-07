<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'lessons_percentage',
        'exam_percentage',
        'final_score',
    ];

    protected $casts = [
        'lessons_percentage' => 'decimal:2',
        'exam_percentage' => 'decimal:2',
        'final_score' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
