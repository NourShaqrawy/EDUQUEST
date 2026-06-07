<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\ApiException;
use App\Models\Course;

trait ManagesCourses
{
    /** يتأكد أن المستخدم الحالي أدمن أو صاحب الكورس. */
    protected function assertManagesCourse(Course $course): void
    {
        $user = request()->user();

        if ($user->role !== 'admin' && $course->publisher_id !== $user->id) {
            throw ApiException::forbidden('لا تملك صلاحية إدارة هذا الكورس.');
        }
    }
}
