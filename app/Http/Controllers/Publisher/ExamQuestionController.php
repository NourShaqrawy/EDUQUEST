<?php

namespace App\Http\Controllers\Publisher;

use App\Exceptions\ExamException;
use App\Http\Controllers\Concerns\ManagesCourses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Publisher\StoreExamQuestionRequest;
use App\Http\Requests\Publisher\UpdateExamQuestionRequest;
use App\Http\Resources\ExamQuestionResource;
use App\Models\Course;
use App\Models\ExamQuestion;
use App\Services\PublisherExamService;

class ExamQuestionController extends Controller
{
    use ManagesCourses;

    public function __construct(private readonly PublisherExamService $exams) {}

    /** إضافة سؤال امتحان مع خياراته. */
    public function store(StoreExamQuestionRequest $request, Course $course)
    {
        $this->assertManagesCourse($course);

        $exam = $course->exam;

        if (! $exam) {
            throw ExamException::notConfigured();
        }

        $question = $this->exams->addQuestion($exam, $request->validated());

        return $this->success(ExamQuestionResource::make($question), 'تمت إضافة السؤال.', 201);
    }

    /** تعديل سؤال امتحان (واستبدال خياراته إن أُرسلت). */
    public function update(UpdateExamQuestionRequest $request, ExamQuestion $examQuestion)
    {
        $this->assertManagesCourse($examQuestion->exam->course);

        $question = $this->exams->updateQuestion($examQuestion, $request->validated());

        return $this->success(ExamQuestionResource::make($question), 'تم تعديل السؤال.');
    }

    /** حذف سؤال امتحان. */
    public function destroy(ExamQuestion $examQuestion)
    {
        $this->assertManagesCourse($examQuestion->exam->course);

        $this->exams->deleteQuestion($examQuestion);

        return $this->message('تم حذف السؤال.');
    }
}
