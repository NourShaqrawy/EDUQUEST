<?php

namespace App\Services;

use App\Exceptions\EnrollmentException;
use App\Exceptions\ExamException;
use App\Jobs\ExamFinalCountdown;
use App\Jobs\FinalizeExamAttempt;
use App\Models\Course;
use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
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
            ->first();

        if ($existing) {
            if ($existing->isFinalized()) {
                throw ExamException::alreadyAttempted();
            }

            if ($existing->hasTimeExpired()) {
                $this->finalize($existing, ExamAttempt::STATUS_EXPIRED);
                throw ExamException::alreadyAttempted();
            }

            return $existing; // استئناف محاولة قيد التنفيذ بنفس الأسئلة والوقت المتبقّي
        }

        $now = Carbon::now();
        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'course_exam_id' => $exam->id,
            'started_at' => $now,
            'ends_at' => $now->copy()->addMinutes($exam->duration_minutes),
            'status' => ExamAttempt::STATUS_IN_PROGRESS,
        ]);

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

    private function runningAttemptOrFail(User $user, Course $course): ExamAttempt
    {
        $exam = $course->exam;

        if (! $exam) {
            throw ExamException::notConfigured();
        }

        $attempt = ExamAttempt::where('user_id', $user->id)
            ->where('course_exam_id', $exam->id)
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
        $question = ExamQuestion::where('id', $questionId)
            ->where('course_exam_id', $attempt->course_exam_id)
            ->first();

        if (! $question) {
            throw ExamException::requirementsNotMet('هذا السؤال لا يخص امتحان هذا الكورس.');
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
