<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['question_id', 'option_text', 'is_correct'])]
class VideoQuestionOption extends Model
{
    use HasFactory;

    public function question()
    {
        return $this->belongsTo(VideoQuestion::class);
    }
}
