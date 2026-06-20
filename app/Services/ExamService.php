<?php

namespace App\Services;

use App\Exceptions\EnrollmentException;
use App\Exceptions\ExamException;
use App\Jobs\ExamFinalCountdown;
use App\Jobs\FinalizeExamAttempt;
use App\Models\Course;
use App\Models\CourseExam;
use App\Models\CourseResult;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamAttemptEvent;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamQuestion;
use App\Models\ExamQuestionOption;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExamService
{
    public function __construct(
        private readonly LessonProgressService $lessonProgress,
        private readonly ExamGradingService $grading,
    ) {}

    /**
     * يبدأ محاولة جديدة أو يستأنف القائمة. يفرض: التسجيل + نشر الامتحان + إكمال الدروس + محاولة واحدة.
     * يضبط ends_at مرجعيّةً للخادم ويجدول الإنهاء التلقائي والعدّ التنازلي للدقيقة الأخيرة.
     */
    public function startOrResume(User $user, Course $course): ExamAttempt
    {
        $exam = $this->assertAccessibleExam($user, $course);

        $existing = ExamAttempt::where('user_id', $user->id)
            ->where('course_exam_id', $exam->id)
            ->latest()
            ->first();

        if ($existing) {
            if ($existing->isRunning()) {
                return $existing; // استئناف محاولة قيد التنفيذ — الأسئلة مثبّتة في exam_attempt_questions
            }

            if ($existing->hasTimeExpired() && ! $existing->isFinalized()) {
                $this->finalize($existing, ExamAttempt::STATUS_EXPIRED);
            }

            // إذا نجح الطالب سابقاً → لا يُسمح بإعادة التقديم
            $courseResult = CourseResult::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            if ($courseResult && (float) $courseResult->final_score >= ExamGradingService::PASS_THRESHOLD) {
                throw ExamException::alreadyPassed();
            }

            // رسب الطالب → يُسمح بإنشاء محاولة جديدة (fall through)
        }

        $now = Carbon::now();

        $attempt = ExamAttempt::create([
            'user_id'        => $user->id,
            'course_exam_id' => $exam->id,
            'started_at'     => $now,
            'ends_at'        => $now->copy()->addMinutes($exam->duration_minutes),
            'status'         => ExamAttempt::STATUS_IN_PROGRESS,
        ]);

        // سحب questions_to_serve عشوائياً من البنك وتثبيتها على المحاولة.
        $this->assignRandomQuestions($attempt, $exam);

        // شبكة أمان: إنهاء تلقائي عند انقضاء المدة حتى لو لم يعد الطالب أبداً.
        FinalizeExamAttempt::dispatch($attempt->id)->delay($attempt->ends_at);

        // العدّ التنازلي بالثواني للدقيقة الأخيرة عبر WebSocket.
        $finalMinuteAt = $attempt->ends_at->copy()->subMinute();
        ExamFinalCountdown::dispatch($attempt->id)->delay(
            $finalMinuteAt->isPast() ? $now : $finalMinuteAt
        );

        return $attempt;
    }

    /** حفظ تلقائي تدريجي لإجابة واحدة (upsert). يُرفض بعد انتهاء الوقت. */
    public function autosaveAnswer(User $user, Course $course, int $questionId, int $optionId): ExamAttemptAnswer
    {
        $attempt = $this->runningAttemptOrFail($user, $course);

        return $this->upsertAnswer($attempt, $questionId, $optionId);
    }

    /**
     * الإرسال النهائي دفعة واحدة: يحفظ ما تبقّى من إجابات ثم يُنهي المحاولة ويحسب التقييم.
     */
    public function submit(User $user, Course $course, array $answers = []): ExamAttempt
    {
        $attempt = $this->runningAttemptOrFail($user, $course);

        foreach ($answers as $answer) {
            $this->upsertAnswer($attempt, (int) $answer['exam_question_id'], (int) $answer['exam_question_option_id']);
        }

        return $this->finalize($attempt, ExamAttempt::STATUS_SUBMITTED);
    }

    /**
     * الإنهاء المركزي (يستخدمه الإرسال اليدوي والمهام الخلفية). idempotent ومحميّ ضد التسابق بالقفل.
     */
    public function finalize(ExamAttempt $attempt, string $status): ExamAttempt
    {
        if ($attempt->isFinalized()) {
            return $attempt;
        }

        return DB::transaction(function () use ($attempt, $status) {
            $locked = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->first();

            if (! $locked || $locked->isFinalized()) {
                return $locked ?? $attempt;
            }

            $examPercentage = $this->grading->examPercentage($locked);

            $locked->update([
                'status' => $status,
                'submitted_at' => now(),
                'score' => $examPercentage,
            ]);

            $this->grading->recordFinalResult($locked, $examPercentage);

            return $locked;
        });
    }

    /** يتحقق من إتاحة الامتحان للطالب ويُرجع إعداد الامتحان. */
    public function assertAccessibleExam(User $user, Course $course): CourseExam
    {
        if (! $user->isEnrolledIn($course->id)) {
            throw EnrollmentException::notEnrolled();
        }

        $exam = $course->exam;

        if (! $exam) {
            throw ExamException::notConfigured();
        }

        if (! $exam->is_published) {
            throw ExamException::notPublished();
        }

        if (! $this->lessonProgress->hasCompletedAllLessons($user, $course)) {
            throw ExamException::lessonsNotCompleted();
        }

        return $exam;
    }

    /**
     * إنهاء قسري للمحاولة بسبب مخالفة مراقبة. يُسجّل الحدث ثم يُنهي المحاولة
     * بحساب الدرجة من الإجابات المحفوظة (STATUS_SUBMITTED). idempotent.
     */
    public function terminate(User $user, Course $course, string $reason = 'violation'): ExamAttempt
    {
        $attempt = $this->attemptForEvents($user, $course);

        if (! $attempt) {
            throw ExamException::notInProgress();
        }

        ExamAttemptEvent::create([
            'exam_attempt_id' => $attempt->id,
            'type'            => 'terminated',
            'meta'            => ['reason' => $reason],
            'client_at'       => null,
        ]);

        return $this->finalize($attempt, ExamAttempt::STATUS_SUBMITTED);
    }

    /**
     * يُرجع محاولة الطالب للكورس بصرف النظر عن حالتها (نشطة / منتهية / مرسَلة).
     * يُستخدم لتسجيل أحداث المراقبة فقط — المراقبة best-effort، لا نرفض الدفعة الأخيرة
     * إن انتهى وقت المحاولة للتو، لأن هذه الأحداث أدلّة تدقيق وليست إجابات.
     */
    public function attemptForEvents(User $user, Course $course): ?ExamAttempt
    {
        $exam = $course->exam;

        if (! $exam) {
            return null;
        }

        return ExamAttempt::where('user_id', $user->id)
            ->where('course_exam_id', $exam->id)
            ->latest()
            ->first();
    }

    /**
     * يسحب questions_to_serve أسئلة عشوائية من بنك الامتحان ويثبّتها على المحاولة.
     * إذا لم يُضبط questions_to_serve (بيانات قديمة)، يأخذ كل الأسئلة.
     */
    private function assignRandomQuestions(ExamAttempt $attempt, CourseExam $exam): void
    {
        $toServe = $exam->questions_to_serve;

        $questionIds = $exam->questions()->pluck('id')->shuffle();

        if ($toServe && $toServe < $questionIds->count()) {
            $questionIds = $questionIds->take($toServe);
        }

        $rows = $questionIds->values()->map(fn ($id, $idx) => [
            'exam_attempt_id'  => $attempt->id,
            'exam_question_id' => $id,
            'display_order'    => $idx + 1,
        ])->all();

        ExamAttemptQuestion::insert($rows);
    }

    private function runningAttemptOrFail(User $user, Course $course): ExamAttempt
    {
        $exam = $course->exam;

        if (! $exam) {
            throw ExamException::notConfigured();
        }

        $attempt = ExamAttempt::where('user_id', $user->id)
            ->where('course_exam_id', $exam->id)
            ->latest()
            ->first();

        if (! $attempt || $attempt->isFinalized()) {
            throw $attempt && $attempt->isFinalized()
                ? ExamException::alreadyAttempted()
                : ExamException::notInProgress();
        }

        if ($attempt->hasTimeExpired()) {
            $this->finalize($attempt, ExamAttempt::STATUS_EXPIRED);
            throw ExamException::timeExpired();
        }

        return $attempt;
    }

    private function upsertAnswer(ExamAttempt $attempt, int $questionId, int $optionId): ExamAttemptAnswer
    {
        // التحقق أن السؤال ضمن الأسئلة المثبّتة على هذه المحاولة تحديداً.
        $inAttempt = ExamAttemptQuestion::where('exam_attempt_id', $attempt->id)
            ->where('exam_question_id', $questionId)
            ->exists();

        if (! $inAttempt) {
            throw ExamException::requirementsNotMet('هذا السؤال لا يخص محاولتك الحالية.');
        }

        $question = ExamQuestion::find($questionId);

        if (! $question) {
            throw ExamException::requirementsNotMet('هذا السؤال غير موجود.');
        }

        $option = ExamQuestionOption::where('id', $optionId)
            ->where('exam_question_id', $questionId)
            ->first();

        if (! $option) {
            throw ExamException::requirementsNotMet('الخيار المُرسَل لا يخص هذا السؤال.');
        }

        return ExamAttemptAnswer::updateOrCreate(
            ['exam_attempt_id' => $attempt->id, 'exam_question_id' => $questionId],
            ['exam_question_option_id' => $option->id, 'is_correct' => $option->is_correct],
        );
    }
}
