<?php

use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// إشعارات المستخدم: لا يُصرّح بالقناة إلا لصاحبها.
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return (int) $user->id === (int) $userId;
});

// قناة عدّاد محاولة الامتحان: لا يُصرّح بها إلا لصاحب المحاولة نفسه.
Broadcast::channel('exam-attempt.{attemptId}', function ($user, int $attemptId) {
    return ExamAttempt::where('id', $attemptId)
        ->where('user_id', $user->id)
        ->exists();
});
