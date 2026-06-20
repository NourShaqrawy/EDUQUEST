<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    /** كورسات الطالب المسجَّلة مع نسبة تقدّم المشاهدة لكل دورة. */
    public function index(Request $request)
    {
        $user = $request->user();

        $enrollments = $user->enrollments()
            ->with(['course', 'course.courseVideos'])
            ->latest('enrolled_at')
            ->get();

        // جلب جميع IDs الفيديوهات المشاهَدة في استعلام واحد (تجنّب N+1)
        $allVideoIds = $enrollments
            ->flatMap(fn ($e) => $e->course?->courseVideos->pluck('id') ?? collect())
            ->filter()
            ->values();

        $watchedSet = $allVideoIds->isEmpty()
            ? collect()
            : LessonProgress::where('user_id', $user->id)
                ->whereIn('course_video_id', $allVideoIds)
                ->pluck('course_video_id')
                ->flip();

        foreach ($enrollments as $enrollment) {
            $videoIds = $enrollment->course?->courseVideos->pluck('id') ?? collect();
            $total    = $videoIds->count();
            $watched  = $videoIds->filter(fn ($id) => $watchedSet->has($id))->count();

            $enrollment->setAttribute('progress', [
                'total'      => $total,
                'watched'    => $watched,
                'percentage' => $total > 0 ? (int) round($watched / $total * 100) : 0,
            ]);
        }

        return $this->success(EnrollmentResource::collection($enrollments));
    }

    /** التسجيل في كورس. */
    public function store(Request $request, Course $course)
    {
        $enrollment = $this->enrollments->enroll($request->user(), $course);

        return $this->success(
            EnrollmentResource::make($enrollment),
            'تم التسجيل في الكورس بنجاح.',
            201
        );
    }
}
