<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamAttemptEvent extends Model
{
    use HasFactory;

    protected $fillable = ['exam_attempt_id', 'type', 'meta', 'client_at'];

    protected $casts = [
        'meta'      => 'array',
        'client_at' => 'datetime',
    ];

    public function attempt()
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }
}
