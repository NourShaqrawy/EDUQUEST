<?php

namespace App\Services;

use App\Exceptions\EnrollmentException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

class EnrollmentService
{
    /** تسجيل الطالب في كورس. لا يُسمح بالتسجيل المكرر. */
    public function enroll(User $user, Course $course): Enrollment
    {
        if ($user->isEnrolledIn($course->id)) {
            throw EnrollmentException::alreadyEnrolled();
        }

        return Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
        ]);
    }
}
