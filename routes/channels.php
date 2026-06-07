<?php

use App\Models\ExamAttempt;
use Illuminate\Support\Facades\Broadcast;

/**
 * قناة عدّاد محاولة الامتحان: لا يُصرّح بها إلا لصاحب المحاولة نفسه.
 */
Broadcast::channel('exam-attempt.{attemptId}', function ($user, int $attemptId) {
    return ExamAttempt::where('id', $attemptId)
        ->where('user_id', $user->id)
        ->exists();
});
