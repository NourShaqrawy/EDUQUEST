<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseSummaryResource;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\CourseVideo;
use App\Models\LessonProgress;
use App\Models\VideoQuestionAnswer;
use App\Services\LessonProgressService;
use Illuminate\Http\Request;

class StudentCourseController extends Controller
{
    public function __construct(private readonly LessonProgressService $lessonProgress) {}

    /**
     * دروس الكورس للطالب مع حالات can_watch / is_watched / is_completed وملخّص التقدّم.
     * غير المسجَّل يرى الفيديو الأول فقط؛ المسجَّل يخضع للفتح التسلسلي.
     */
    public function lessons(Request $request, Course $course)
    {
        $user = $request->user();
        $videos = $course->courseVideos()->withCount('videoQuestions')->get();
        $videoIds = $videos->pluck('id');

        $answeredPerVideo = VideoQuestionAnswer::query()
            ->join('video_questions', 'video_questions.id', '=', 'video_question_answers.question_id')
            ->where('video_question_answers.user_id', $user->id)
            ->whereIn('video_questions.video_id', $videoIds)
            ->selectRaw('video_questions.video_id as video_id, COUNT(*) as answered_count')
            ->groupBy('video_questions.video_id')
            ->pluck('answered_count', 'video_id');

        $watchedIds = LessonProgress::where('user_id', $user->id)
            ->whereIn('course_video_id', $videoIds)
            ->pluck('course_video_id')
            ->flip();

        $isEnrolled = $user->isEnrolledIn($course->id);
        $allPreviousCompleted = true;

        foreach ($videos as $index => $video) {
            $totalQuestions = (int) $video->video_questions_count;
            $answeredCount = (int) ($answeredPerVideo[$video->id] ?? 0);

            // إكمال الدرس (للفتح التسلسلي) = الإجابة على كل أسئلته.
            $answeredAll = $totalQuestions === 0 ? true : $answeredCount >= $totalQuestions;
            $isFirst = $index === 0;

            // الأول متاح للجميع (معاينة)؛ غير الأول يتطلب التسجيل + إكمال كل ما قبله.
            $video->can_watch = $isFirst ? true : ($isEnrolled && $allPreviousCompleted);
            $video->is_watched = $watchedIds->has($video->id);
            $video->is_completed = $answeredAll;
            $video->makeHidden('video_questions_count');

            $allPreviousCompleted = $allPreviousCompleted && $answeredAll;
        }

        return $this->success([
            'course' => CourseSummaryResource::make($course),
            'is_enrolled' => $isEnrolled,
            'progress' => $this->lessonProgress->progressSummary($user, $course),
            'videos' => LessonResource::collection($videos),
        ]);
    }

    /** تسجيل مشاهدة درس. */
    public function markWatched(Request $request, Course $course, CourseVideo $video)
    {
        if ($video->course_id !== $course->id) {
            throw ApiException::notFound('هذا الدرس لا يخص هذا الكورس.');
        }

        $this->lessonProgress->markWatched($request->user(), $video);

        return $this->message('تم تسجيل مشاهدة الدرس.');
    }
}
