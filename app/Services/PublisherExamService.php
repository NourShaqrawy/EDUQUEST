<?php

namespace App\Services;

use App\Exceptions\ExamException;
use App\Models\Course;
use App\Models\CourseExam;
use App\Models\ExamQuestion;
use Illuminate\Support\Facades\DB;

class PublisherExamService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** إنشاء/تحديث إعداد امتحان الكورس (المدة بالدقائق). */
    public function upsertExam(Course $course, int $durationMinutes): CourseExam
    {
        return CourseExam::updateOrCreate(
            ['course_id' => $course->id],
            ['duration_minutes' => $durationMinutes],
        );
    }

    /** إضافة سؤال مع خياراته. لا يُسمح بالتعديل والامتحان منشور (حفاظاً على نزاهة الامتحان). */
    public function addQuestion(CourseExam $exam, array $data): ExamQuestion
    {
        $this->assertEditable($exam);

        return DB::transaction(function () use ($exam, $data) {
            $question = $exam->questions()->create(['question' => $data['question']]);
            $this->syncOptions($question, $data['options']);

            return $question->load('options');
        });
    }

    /** تعديل سؤال (واستبدال خياراته بالكامل إن أُرسلت). */
    public function updateQuestion(ExamQuestion $question, array $data): ExamQuestion
    {
        $this->assertEditable($question->exam);

        return DB::transaction(function () use ($question, $data) {
            if (array_key_exists('question', $data)) {
                $question->update(['question' => $data['question']]);
            }

            if (array_key_exists('options', $data)) {
                $question->options()->delete();
                $this->syncOptions($question, $data['options']);
            }

            return $question->load('options');
        });
    }

    /** حذف سؤال. */
    public function deleteQuestion(ExamQuestion $question): void
    {
        $this->assertEditable($question->exam);
        $question->delete(); // cascade يحذف الخيارات
    }

    /** نشر الامتحان بعد التحقق من استيفاء كل القيود. */
    public function publish(CourseExam $exam): CourseExam
    {
        $questions = $exam->questions()->with('options')->get();
        $count = $questions->count();

        if ($count < CourseExam::MIN_QUESTIONS || $count > CourseExam::MAX_QUESTIONS) {
            throw ExamException::requirementsNotMet(
                'عدد الأسئلة يجب أن يكون بين '.CourseExam::MIN_QUESTIONS.' و'.CourseExam::MAX_QUESTIONS." (الحالي {$count})."
            );
        }

        foreach ($questions as $question) {
            $optionsCount = $question->options->count();

            if ($optionsCount < CourseExam::MIN_OPTIONS || $optionsCount > CourseExam::MAX_OPTIONS) {
                throw ExamException::requirementsNotMet("السؤال رقم {$question->id} يجب أن يحتوي على خيارين إلى أربعة خيارات.");
            }

            if ($question->options->where('is_correct', true)->count() !== 1) {
                throw ExamException::requirementsNotMet("السؤال رقم {$question->id} يجب أن يحتوي على إجابة صحيحة واحدة فقط.");
            }
        }

        $exam->update(['is_published' => true]);

        // إشعار كل الطلاب المسجّلين في الكورس بأن الامتحان أصبح متاحاً
        $course = $exam->course;
        $this->notifications->sendToMany(
            $course->enrollments()->pluck('user_id'),
            'امتحان جديد متاح',
            "أصبح امتحان كورس \"{$course->title}\" متاحاً الآن. أكمل الدروس لتتمكّن من دخوله.",
            'exam_published',
            ['course_id' => $course->id],
        );

        return $exam;
    }

    public function unpublish(CourseExam $exam): CourseExam
    {
        $exam->update(['is_published' => false]);

        return $exam;
    }

    private function assertEditable(CourseExam $exam): void
    {
        if ($exam->is_published) {
            throw ExamException::requirementsNotMet('يجب إلغاء نشر الامتحان قبل تعديل أسئلته.');
        }
    }

    private function syncOptions(ExamQuestion $question, array $options): void
    {
        foreach ($options as $option) {
            $question->options()->create([
                'option_text' => $option['option_text'],
                'is_correct' => filter_var($option['is_correct'], FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
