<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['question_id', 'option_text', 'is_correct'])]
class VideoQuestionOption extends Model
{
    use HasFactory;

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function question()
    {
        return $this->belongsTo(VideoQuestion::class);
    }
}
