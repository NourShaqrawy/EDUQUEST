<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\AutosaveExamAnswerRequest;
use App\Http\Requests\Student\SubmitExamRequest;
use App\Http\Resources\CourseResultResource;
use App\Http\Resources\ExamAttemptResource;
use App\Http\Resources\ExamQuestionResource;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use App\Services\LessonProgressService;
use Illuminate\Http\Request;

class StudentExamController extends Controller
{
    public function __construct(
        private readonly ExamService $exams,
        private readonly LessonProgressService $lessonProgress,
    ) {}

    /** نظرة عامة على امتحان الكورس وجاهزية الطالب لدخوله (بدون بدء المحاولة). */
    public function show(Request $request, Course $course)
    {
        $user = $request->user();
        $exam = $course->exam()->withCount('questions')->first();

        $attempt = $exam
            ? ExamAttempt::where('user_id', $user->id)->where('course_exam_id', $exam->id)->first()
            : null;

        $isEnrolled = $user->isEnrolledIn($course->id);
        $lessonsCompleted = $this->lessonProgress->hasCompletedAllLessons($user, $course);

        return $this->success([
            'is_enrolled' => $isEnrolled,
            'lessons_completed' => $lessonsCompleted,
            'exam' => $exam ? [
                'duration_minutes' => $exam->duration_minutes,
                'questions_count' => $exam->questions_count,
                'is_published' => $exam->is_published,
            ] : null,
            'attempt' => $attempt ? ExamAttemptResource::make($attempt) : null,
            'can_start' => $isEnrolled
                && $exam?->is_published
                && $lessonsCompleted
                && ! ($attempt && $attempt->isFinalized()),
        ]);
    }

    /** بدء/استئناف الامتحان. يُرجع المحاولة (مع الوقت المتبقّي) والأسئلة دون الإجابة الصحيحة. */
    public function start(Request $request, Course $course)
    {
        $attempt = $this->exams->startOrResume($request->user(), $course);

        $questions = $course->exam->questions()->with('options')->get();

        return $this->success([
            'attempt' => ExamAttemptResource::make($attempt),
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

        return $this->success([
            'attempt' => ExamAttemptResource::make($attempt->fresh()),
            'result' => $result ? CourseResultResource::make($result) : null,
        ], 'تم تسليم الامتحان واحتساب التقييم.');
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
