<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CourseCertificateController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseVideoController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Publisher\ExamQuestionController; // استيراد المتحكم الجديد
use App\Http\Controllers\Publisher\PublisherExamController;
use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Controllers\Student\StudentCourseController;
use App\Http\Controllers\Student\StudentExamController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoQuestionAnswerController;
use App\Http\Controllers\VideoQuestionController;
use App\Http\Controllers\VideoQuestionOptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (المسارات العامة المتاحة للجميع)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// التحقق من شهادة — عام بدون مصادقة
Route::get('/certificates/verify/{code}', [CourseCertificateController::class, 'verify']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

// مسارات عرض الكورسات متاحة للزوار أيضاً
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{id}', [CourseController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes (المسارات التي تتطلب تسجيل دخول)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    // عرض الأسئلة والخيارات وإرسال الإجابات (متاح لجميع المستخدمين المسجلين)
    Route::get('/videos/{video_id}/questions', [VideoQuestionController::class, 'index']);
    Route::get('/video-questions/{id}', [VideoQuestionController::class, 'show']);

    Route::get('/questions/{question_id}/options', [VideoQuestionOptionController::class, 'index']);
    Route::get('/video-options/{id}', [VideoQuestionOptionController::class, 'show']);

    Route::get('/video-answers', [VideoQuestionAnswerController::class, 'index']);
    Route::post('/video-answers', [VideoQuestionAnswerController::class, 'store']);
    Route::get('/video-answers/{id}', [VideoQuestionAnswerController::class, 'show']);

    // 🔔 الإشعارات (متاحة لجميع المستخدمين المسجلين على إشعاراتهم الخاصة)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    /*
    |----------------------------------------------------------------------
    | 🟢 الطالب (role:user): التسجيل، الدروس، الامتحان، التقييم
    |----------------------------------------------------------------------
    */
    Route::middleware('role:user')->group(function () {
        // التسجيل في الكورسات
        Route::get('/my-enrollments', [EnrollmentController::class, 'index']);
        Route::post('/courses/{course}/enroll', [EnrollmentController::class, 'store']);

        // دروس الكورس وحالة المشاهدة/الإكمال
        Route::get('/courses/{course}/lessons', [StudentCourseController::class, 'lessons']);
        Route::post('/courses/{course}/lessons/{video}/watch', [StudentCourseController::class, 'markWatched']);

        // امتحان الكورس النهائي
        Route::get('/courses/{course}/exam', [StudentExamController::class, 'show']);
        Route::post('/courses/{course}/exam/start', [StudentExamController::class, 'start']);
        Route::post('/courses/{course}/exam/answers', [StudentExamController::class, 'autosave']);
        Route::post('/courses/{course}/exam/submit', [StudentExamController::class, 'submit']);
        Route::post('/courses/{course}/exam/events', [StudentExamController::class, 'events']);
        Route::post('/courses/{course}/exam/terminate', [StudentExamController::class, 'terminate']);
        Route::get('/courses/{course}/exam/result', [StudentExamController::class, 'result']);

        // 🏆 شهادة الكورس
        Route::get('/my-certificates', [CourseCertificateController::class, 'myAll']);
        Route::get('/courses/{course}/certificate', [CourseCertificateController::class, 'show']);
        Route::post('/courses/{course}/certificate', [CourseCertificateController::class, 'store']);
        Route::get('/courses/{course}/certificate/download', [CourseCertificateController::class, 'download']);
    });

    // 🟢 Routes خاصة بالـ Admin فقط
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // مراجعة الكورسات — الأدمن فقط
        Route::get('/pending-courses', [CourseController::class, 'pending']);
        Route::get('/rejected-courses', [CourseController::class, 'rejected']);
        Route::post('/courses/{id}/approve', [CourseController::class, 'approve']);
        Route::post('/courses/{id}/reject', [CourseController::class, 'reject']);
    });

    // 🟢 Routes خاصة بالـ Admin والـ Publisher (إدارة الكورسات)
    Route::middleware('role:admin,publisher')->group(function () {
        // إدارة الكورسات
        Route::get('/my-courses', [CourseController::class, 'myCourses']); // عرض كورسات الناشر نفسه
        Route::post('/courses', [CourseController::class, 'store']);      // إنشاء كورس
        Route::post('/courses/{id}', [CourseController::class, 'update']); // تعديل كورس (استخدمنا POST لأن Laravel يواجه مشاكل أحياناً في قراءة ملفات الصور مع PUT)
        Route::delete('/courses/{id}', [CourseController::class, 'destroy']); // حذف كورس

        // إدارة دروس الكورس
        Route::get('/courses/{courseId}/videos', [CourseVideoController::class, 'index']);
        Route::post('/courses/{courseId}/videos', [CourseVideoController::class, 'store']);
        Route::post('/courses/{courseId}/videos/{videoId}', [CourseVideoController::class, 'update']);
        Route::delete('/courses/{courseId}/videos/{videoId}', [CourseVideoController::class, 'destroy']);

        // إدارة أسئلة الفيديو وخياراتها
        Route::post('/video-questions', [VideoQuestionController::class, 'store']);
        Route::put('/video-questions/{id}', [VideoQuestionController::class, 'update']);
        Route::delete('/video-questions/{id}', [VideoQuestionController::class, 'destroy']);

        Route::post('/video-options', [VideoQuestionOptionController::class, 'store']);
        Route::put('/video-options/{id}', [VideoQuestionOptionController::class, 'update']);
        Route::delete('/video-options/{id}', [VideoQuestionOptionController::class, 'destroy']);

        // حذف الإجابات إذا لزم الأمر
        Route::delete('/video-answers/{id}', [VideoQuestionAnswerController::class, 'destroy']);

        // إدارة امتحان الكورس النهائي
        Route::get('/courses/{course}/exam/manage', [PublisherExamController::class, 'show']);
        Route::post('/courses/{course}/exam', [PublisherExamController::class, 'upsert']);
        Route::post('/courses/{course}/exam/publish', [PublisherExamController::class, 'publish']);
        Route::post('/courses/{course}/exam/unpublish', [PublisherExamController::class, 'unpublish']);

        // إدارة أسئلة الامتحان
        Route::post('/courses/{course}/exam/questions', [ExamQuestionController::class, 'store']);
        Route::put('/exam-questions/{examQuestion}', [ExamQuestionController::class, 'update']);
        Route::delete('/exam-questions/{examQuestion}', [ExamQuestionController::class, 'destroy']);

        // إرسال إشعار إلى مستخدم (وبثّه لحظياً عبر Reverb)
        Route::post('/notifications', [NotificationController::class, 'store']);
    });

    // 🟢 Routes خاصة بالـ User والـ Publisher (تعديل البروفايل)
    Route::middleware('role:publisher,user')->group(function () {
        Route::put('/users/profile', [UserController::class, 'update']);
    });

});
