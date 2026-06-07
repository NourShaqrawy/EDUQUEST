<?php

namespace App\Http\Controllers\Publisher;

use App\Exceptions\ExamException;
use App\Http\Controllers\Concerns\ManagesCourses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Publisher\UpsertCourseExamRequest;
use App\Http\Resources\ExamResource;
use App\Models\Course;
use App\Services\PublisherExamService;

class PublisherExamController extends Controller
{
    use ManagesCourses;

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

        $exam = $this->exams->upsertExam($course, (int) $request->validated()['duration_minutes']);

        return $this->success(
            ExamResource::make($exam->loadCount('questions')),
            'تم حفظ إعدادات الامتحان.'
        );
    }

    /** نشر الامتحان (بعد التحقق من القيود). */
    public function publish(Course $course)
    {
        $this->assertManagesCourse($course);

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

        $exam = $course->exam;

        if (! $exam) {
            throw ExamException::notConfigured();
        }

        $this->exams->unpublish($exam);

        return $this->success(ExamResource::make($exam->loadCount('questions')), 'تم إلغاء نشر الامتحان.');
    }
}
