<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exercise_Submissions extends Model
{
    public function student()
    {
        return $this->belongsTo(User::class,'student_id');
    }
    public function exercise()
    {
        return $this->belongsTo(Exercise::class,'exercise_id');
    }
}
