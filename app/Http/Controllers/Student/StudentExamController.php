<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\AutosaveExamAnswerRequest;
use App\Http\Requests\Student\StoreExamEventsRequest;
use App\Http\Requests\Student\SubmitExamRequest;
use App\Http\Resources\CourseResultResource;
use App\Http\Resources\ExamAttemptResource;
use App\Http\Resources\ExamQuestionResource;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptEvent;
use App\Services\CertificateService;
use App\Services\ExamGradingService;
use App\Services\ExamService;
use App\Services\LessonProgressService;
use Illuminate\Http\Request;

class StudentExamController extends Controller
{
    public function __construct(
        private readonly ExamService $exams,
        private readonly LessonProgressService $lessonProgress,
        private readonly CertificateService $certificates,
    ) {}

    /** نظرة عامة على امتحان الكورس وجاهزية الطالب لدخوله (بدون بدء المحاولة). */
    public function show(Request $request, Course $course)
    {
        $user = $request->user();
        $exam = $course->exam()->withCount('questions')->first();

        $attempt = $exam
            ? ExamAttempt::where('user_id', $user->id)
                ->where('course_exam_id', $exam->id)
                ->latest()
                ->first()
            : null;

        $isEnrolled = $user->isEnrolledIn($course->id);
        $lessonsCompleted = $this->lessonProgress->hasCompletedAllLessons($user, $course);

        // التحقق من النجاح السابق لمنع إعادة التقديم بعد الاجتياز.
        $alreadyPassed = false;
        if ($attempt?->isFinalized()) {
            $courseResult = CourseResult::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();
            $alreadyPassed = $courseResult &&
                (float) $courseResult->final_score >= ExamGradingService::PASS_THRESHOLD;
        }

        return $this->success([
            'is_enrolled' => $isEnrolled,
            'has_certificate' => (bool) $course->has_certificate,
            'lessons_completed' => $lessonsCompleted,
            'exam' => $exam ? [
                'duration_minutes'   => $exam->duration_minutes,
                'questions_count'    => $exam->questions_to_serve ?? $exam->questions_count,
                'is_published'       => $exam->is_published,
            ] : null,
            'attempt' => $attempt ? ExamAttemptResource::make($attempt) : null,
            'can_start' => $isEnrolled
                && $exam?->is_published
                && $lessonsCompleted
                && ! ($attempt && $attempt->isFinalized() && $alreadyPassed),
        ]);
    }

    /** بدء/استئناف الامتحان. يُرجع المحاولة (مع الوقت المتبقّي) والأسئلة المثبّتة بترتيبها العشوائي. */
    public function start(Request $request, Course $course)
    {
        if (! $course->has_certificate) {
            throw ApiException::unprocessable('هذا الكورس تعريفي ولا يحتوي على امتحان.');
        }

        $attempt = $this->exams->startOrResume($request->user(), $course);

        // جلب الأسئلة المثبّتة على هذه المحاولة بترتيبها العشوائي المحفوظ.
        $questions = $attempt->attemptQuestions()
            ->with('question.options')
            ->get()
            ->map(fn ($aq) => $aq->question);

        return $this->success([
            'attempt'   => ExamAttemptResource::make($attempt),
            'questions' => ExamQuestionResource::collection($questions),
        ], 'تم بدء الامتحان.');
    }

    /** حفظ تلقائي تدريجي لإجابة واحدة أثناء الامتحان. */
    public function autosave(AutosaveExamAnswerRequest $request, Course $course)
    {
        $data = $request->validated();

        $this->exams->autosaveAnswer(
            $request->user(),
            $course,
            (int) $data['exam_question_id'],
            (int) $data['exam_question_option_id'],
        );

        return $this->message('تم حفظ الإجابة.');
    }

    /** الإرسال النهائي دفعة واحدة ثم حساب التقييم وعرضه فوراً. */
    public function submit(SubmitExamRequest $request, Course $course)
    {
        $user = $request->user();

        $attempt = $this->exams->submit($user, $course, $request->validated()['answers'] ?? []);

        $result = CourseResult::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        // إصدار الشهادة تلقائياً إذا كانت النتيجة مؤهّلة (صامت — لا يُوقف الاستجابة عند الفشل).
        if ($result && (float) $result->final_score >= 60) {
            try { $this->certificates->requestCertificate($user, $course); } catch (\Throwable) {}
        }

        return $this->success([
            'attempt' => ExamAttemptResource::make($attempt->fresh()),
            'result' => $result ? CourseResultResource::make($result) : null,
        ], 'تم تسليم الامتحان واحتساب التقييم.');
    }

    /** تسجيل أحداث المراقبة (خروج من ملء الشاشة، نسخ/لصق، مغادرة تبويب ...) دفعةً. */
    public function events(StoreExamEventsRequest $request, Course $course)
    {
        $attempt = $this->exams->attemptForEvents($request->user(), $course);

        // لا توجد محاولة → لا شيء نربط به الأحداث. نتجاهل بهدوء (المراقبة best-effort).
        if (! $attempt) {
            return $this->message('لا توجد محاولة امتحان نشطة.', 200);
        }

        $now = now();

        $rows = collect($request->validated()['events'])->map(fn ($e) => [
            'exam_attempt_id' => $attempt->id,
            'type'            => $e['type'],
            'meta'            => isset($e['meta']) ? json_encode($e['meta']) : null,
            // نُطبّع client_at إلى صيغة MySQL — الفرونت يُرسلها ISO 8601 بـ ms وZ suffix
            'client_at'       => isset($e['client_at'])
                                    ? now()->parse($e['client_at'])->format('Y-m-d H:i:s')
                                    : null,
            'created_at'      => $now,  // ساعة الخادم — المرجعية الموثوقة
            'updated_at'      => $now,
        ])->all();

        ExamAttemptEvent::insert($rows);

        // §3 الضمان الدفاعي: fullscreen_exit في الدفعة → إنهاء فوري من الخادم.
        // يغلق الثغرة حتى لو أوقف الطالب استدعاء /terminate كليّاً.
        $hasFullscreenExit = collect($request->validated()['events'])
            ->contains(fn ($e) => ($e['type'] ?? null) === 'fullscreen_exit');

        if ($hasFullscreenExit && ! $attempt->isFinalized()) {
            $this->exams->finalize($attempt, ExamAttempt::STATUS_SUBMITTED);
        }

        return $this->success(['received' => count($rows)], 'تم تسجيل الأحداث.');
    }

    /** إنهاء قسري بسبب مخالفة مراقبة (الخروج من ملء الشاشة). يُرجع نفس شكل submit. */
    public function terminate(Request $request, Course $course)
    {
        $user   = $request->user();
        $reason = (string) ($request->input('reason') ?? 'violation');

        $attempt = $this->exams->terminate($user, $course, $reason);

        $result = CourseResult::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        // إصدار الشهادة تلقائياً إذا كانت النتيجة مؤهّلة (صامت).
        if ($result && (float) $result->final_score >= 60) {
            try { $this->certificates->requestCertificate($user, $course); } catch (\Throwable) {}
        }

        return $this->success([
            'attempt' => ExamAttemptResource::make($attempt->fresh()),
            'result'  => $result ? CourseResultResource::make($result) : null,
        ], 'تم إنهاء الامتحان.');
    }

    /** التقييم النهائي للطالب في الكورس. */
    public function result(Request $request, Course $course)
    {
        $result = CourseResult::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        return $this->success(CourseResultResource::make($result));
    }
}
