<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'question_id', 'option_id', 'is_correct'])]
class VideoQuestionAnswer extends Model
{
    use HasFactory;

    public $timestamps = false; // لأن الجدول يحتوي فقط created_at

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function question()
    {
        return $this->belongsTo(VideoQuestion::class);
    }

    public function option()
    {
        return $this->belongsTo(VideoQuestionOption::class);
    }
}
