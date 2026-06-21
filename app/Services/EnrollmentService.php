<?php

namespace App\Services;

use App\Exceptions\EnrollmentException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

class EnrollmentService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** تسجيل الطالب في كورس. لا يُسمح بالتسجيل المكرر. */
    public function enroll(User $user, Course $course): Enrollment
    {
        if ($user->isEnrolledIn($course->id)) {
            throw EnrollmentException::alreadyEnrolled();
        }

        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
        ]);

        $this->notifications->send(
            $user->id,
            'تم تسجيلك في كورس جديد',
            "لقد سجّلت في كورس \"{$course->title}\" بنجاح.",
            'enrollment',
            [
                'course_id' => $course->id,
                'title_en'  => 'You have enrolled in a new course',
                'title_ar'  => 'تم تسجيلك في كورس جديد',
                'body_en'   => "You have successfully enrolled in \"{$course->title}\".",
                'body_ar'   => "لقد سجّلت في كورس \"{$course->title}\" بنجاح.",
            ],
        );

        return $enrollment;
    }
}
