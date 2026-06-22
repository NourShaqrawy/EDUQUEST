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

    /** يمنع تعديل محتوى الكورس بعد جعله مكتملاً — يجب إرجاعه إلى وضع التعديل أولاً. */
    protected function assertCourseEditable(Course $course): void
    {
        if ($course->completion_status === 'completed') {
            throw ApiException::unprocessable(
                'هذا الكورس مكتمل ومقفل. أرجِعه إلى وضع التعديل أولاً لإجراء أي تعديل.'
            );
        }
    }

    /** يمنع إنشاء/تعديل امتحان لكورس تعريفي (بلا شهادة). */
    protected function assertCourseHasCertificate(Course $course): void
    {
        if (! $course->has_certificate) {
            throw ApiException::unprocessable('هذا الكورس تعريفي ولا يمكن إضافة امتحان له.');
        }
    }
}
