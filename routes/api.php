<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CourseController; // استيراد المتحكم الجديد
use App\Http\Controllers\CourseVideoController;
use App\Http\Controllers\VideoQuestionController;
use App\Http\Controllers\VideoQuestionOptionController;
use App\Http\Controllers\VideoQuestionAnswerController;

/*
|--------------------------------------------------------------------------
| Public Routes (المسارات العامة المتاحة للجميع)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

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

    // عرض دروس الكورس للطالب مع حالة الفتح/الإكمال (can_watch / is_completed)
    Route::get('/courses/{courseId}/lessons', [CourseVideoController::class, 'studentIndex']);

    // عرض الأسئلة والخيارات وإرسال الإجابات (متاح لجميع المستخدمين المسجلين)
    Route::get('/videos/{video_id}/questions', [VideoQuestionController::class, 'index']);
    Route::get('/video-questions/{id}', [VideoQuestionController::class, 'show']);
    
    Route::get('/questions/{question_id}/options', [VideoQuestionOptionController::class, 'index']);
    Route::get('/video-options/{id}', [VideoQuestionOptionController::class, 'show']);

    Route::get('/video-answers', [VideoQuestionAnswerController::class, 'index']);
    Route::post('/video-answers', [VideoQuestionAnswerController::class, 'store']);
    Route::get('/video-answers/{id}', [VideoQuestionAnswerController::class, 'show']);

    // 🟢 Routes خاصة بالـ Admin فقط
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
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
    });

    // 🟢 Routes خاصة بالـ User والـ Publisher (تعديل البروفايل)
    Route::middleware('role:publisher,user')->group(function () {
        Route::put('/users/profile', [UserController::class, 'update']);
    });

});
