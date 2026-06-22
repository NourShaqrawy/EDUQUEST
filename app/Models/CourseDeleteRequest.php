<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseDeleteRequest extends Model
{
    protected $fillable = [
        'course_id',
        'publisher_id',
        'reason',
        'status',
        'admin_note',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }
}
