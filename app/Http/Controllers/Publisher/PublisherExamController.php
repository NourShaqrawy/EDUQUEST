<?php

namespace App\Http\Controllers\Publisher;

use App\Exceptions\ApiException;
use App\Exceptions\ExamException;
use App\Http\Controllers\Concerns\ManagesCourses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Publisher\UpsertCourseExamRequest;
use App\Http\Resources\ExamAttemptEventResource;
use App\Http\Resources\ExamAttemptSummaryResource;
use App\Http\Resources\ExamResource;
use App\Models\Course;
use App\Models\ExamAttempt;
use App\Services\PublisherExamService;

class PublisherExamController extends Controller
{
    use ManagesCourses;

    /**
     * أنواع الأحداث التي لا تُعتبر مخالفة (سلوك طبيعي أثناء الامتحان).
     * ما عداها يُحتسب ضمن violations_count كدليل للمراجعة البشرية.
     */
    private const NON_VIOLATION_EVENTS = ['tab_focus', 'fullscreen_enter', 'question_reveal'];

    public function __construct(private readonly PublisherExamService $exams) {}

    /** عرض امتحان الكورس وأسئلته (مع الإجابات الصحيحة) للناشر. */
    public function show(Course $course)
    {
        $this->assertManagesCourse($course);

        $exam = $course->exam()
            ->with('questions.options')
            ->withCount('questions')
            ->first();

        return $this->success($exam ? ExamResource::make($exam) : null);
    }

    /** إنشاء/تحديث إعداد الامتحان (المدة بالدقائق). */
    public function upsert(UpsertCourseExamRequest $request, Course $course)
    {
        $this->assertManagesCourse($course);
        $this->assertCourseHasCertificate($course);
        $this->assertCourseEditable($course);

        $data = $request->validated();
        $exam = $this->exams->upsertExam(
            $course,
            (int) $data['duration_minutes'],
            (int) $data['questions_to_serve'],
        );

        return $this->success(
            ExamResource::make($exam->loadCount('questions')),
            'تم حفظ إعدادات الامتحان.'
        );
    }

    /** نشر الامتحان (بعد التحقق من القيود). */
    public function publish(Course $course)
    {
        $this->assertManagesCourse($course);
        $this->assertCourseHasCertificate($course);
        $this->assertCourseEditable($course);

        $exam = $course->exam;

        if (! $exam) {
            throw ExamException::notConfigured();
        }

        $this->exams->publish($exam);

        return $this->success(ExamResource::make($exam->loadCount('questions')), 'تم نشر الامتحان.');
    }

    /** إلغاء نشر الامتحان (للسماح بالتعديل). */
    public function unpublish(Course $course)
    {
        $this->assertManagesCourse($course);
        $this->assertCourseHasCertificate($course);
        $this->assertCourseEditable($course);

        $exam = $course->exam;

        if (! $exam) {
            throw ExamException::notConfigured();
        }

        $this->exams->unpublish($exam);

        return $this->success(ExamResource::make($exam->loadCount('questions')), 'تم إلغاء نشر الامتحان.');
    }

    /**
     * سجل مراقبة الامتحان — قائمة محاولات الطلاب على امتحان الكورس.
     * لكل محاولة: الطالب، الحالة، الدرجة، وعدّاد الأحداث/المخالفات (كدليل للمراجعة).
     */
    public function attempts(Course $course)
    {
        $this->assertManagesCourse($course);

        $exam = $course->exam;

        if (! $exam) {
            return $this->success([]);
        }

        $attempts = ExamAttempt::query()
            ->where('course_exam_id', $exam->id)
            ->with('user:id,name,email')
            ->withCount('events')
            ->withCount(['events as violations_count' => function ($q) {
                $q->whereNotIn('type', self::NON_VIOLATION_EVENTS);
            }])
            ->orderByDesc('started_at')
            ->get();

        return $this->success(ExamAttemptSummaryResource::collection($attempts));
    }

    /** الخط الزمني لأحداث المراقبة لمحاولة واحدة (بترتيب ساعة الخادم). */
    public function attemptEvents(Course $course, ExamAttempt $attempt)
    {
        $this->assertManagesCourse($course);

        // امنع تسريب أحداث محاولة تعود لكورس آخر.
        if (! $course->exam || $attempt->course_exam_id !== $course->exam->id) {
            throw ApiException::notFound('المحاولة غير موجودة لهذا الكورس.');
        }

        $events = $attempt->events()->orderBy('created_at')->orderBy('id')->get();

        return $this->success(ExamAttemptEventResource::collection($events));
    }
}
