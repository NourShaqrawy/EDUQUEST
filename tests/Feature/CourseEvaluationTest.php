<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseExam;
use App\Models\CourseVideo;
use App\Models\Enrollment;
use App\Models\ExamQuestion;
use App\Models\LessonProgress;
use App\Models\User;
use App\Models\VideoQuestion;
use App\Models\VideoQuestionAnswer;
use App\Models\VideoQuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private User $publisher;

    private Course $course;

    private CourseVideo $video;

    private VideoQuestion $lessonQuestion;

    private VideoQuestionOption $lessonCorrectOption;

    protected function setUp(): void
    {
        parent::setUp();

        // مؤقّت الامتحان (jobs العدّ التنازلي) غير معني بهذه الاختبارات؛ نمنع تنفيذه.
        Queue::fake();

        $this->publisher = User::create([
            'name' => 'Pub', 'email' => 'pub@test.com',
            'role' => 'publisher', 'password' => bcrypt('password123'),
        ]);

        $this->course = Course::create([
            'title' => 'C', 'description' => 'd', 'thumbnail' => 'x.jpg',
            'category_id' => DB::table('categories')->insertGetId([
                'name' => 'Cat', 'description' => 'd', 'created_at' => now(), 'updated_at' => now(),
            ]),
            'publisher_id' => $this->publisher->id,
        ]);

        $this->video = CourseVideo::create([
            'course_id' => $this->course->id, 'title' => 'V1', 'video_path' => 'p.mp4',
            'duration' => 100, 'order' => 1,
        ]);

        $this->lessonQuestion = VideoQuestion::create([
            'video_id' => $this->video->id, 'question' => 'Q', 'time_in_video' => 5,
        ]);
        $this->lessonCorrectOption = VideoQuestionOption::create([
            'question_id' => $this->lessonQuestion->id, 'option_text' => 'right', 'is_correct' => true,
        ]);
        VideoQuestionOption::create([
            'question_id' => $this->lessonQuestion->id, 'option_text' => 'wrong', 'is_correct' => false,
        ]);
    }

    private function student(): User
    {
        return User::create([
            'name' => 'Stud', 'email' => 'stud@test.com',
            'role' => 'user', 'password' => bcrypt('password123'),
        ]);
    }

    public function test_publish_is_blocked_when_question_count_below_minimum(): void
    {
        Sanctum::actingAs($this->publisher);
        CourseExam::create(['course_id' => $this->course->id, 'duration_minutes' => 30]);

        $this->postJson("/api/courses/{$this->course->id}/exam/publish")
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_exam_is_locked_until_lessons_completed_then_submit_grades(): void
    {
        $student = $this->student();

        // امتحان منشور بـ 10 أسئلة صحيحة البنية.
        $exam = CourseExam::create(['course_id' => $this->course->id, 'duration_minutes' => 30]);
        $examOptions = [];
        for ($i = 0; $i < 10; $i++) {
            $q = ExamQuestion::create(['course_exam_id' => $exam->id, 'question' => "EQ$i"]);
            $correct = $q->options()->create(['option_text' => 'right', 'is_correct' => true]);
            $q->options()->create(['option_text' => 'wrong', 'is_correct' => false]);
            $examOptions[$q->id] = $correct->id;
        }
        $exam->update(['is_published' => true]);

        Sanctum::actingAs($student);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $this->course->id, 'enrolled_at' => now()]);

        // قبل إكمال الدروس: الامتحان مقفل.
        $this->postJson("/api/courses/{$this->course->id}/exam/start")->assertStatus(403);

        // إكمال الدروس: مشاهدة + إجابة كل الأسئلة.
        LessonProgress::create(['user_id' => $student->id, 'course_video_id' => $this->video->id, 'watched_at' => now()]);
        VideoQuestionAnswer::create([
            'user_id' => $student->id, 'question_id' => $this->lessonQuestion->id,
            'option_id' => $this->lessonCorrectOption->id, 'is_correct' => true,
        ]);

        // الآن يبدأ الامتحان.
        $start = $this->postJson("/api/courses/{$this->course->id}/exam/start")->assertOk();
        $this->assertGreaterThan(0, $start->json('data.attempt.remaining_seconds'));
        // الإجابة الصحيحة مخفيّة عن الطالب.
        $this->assertArrayNotHasKey('is_correct', $start->json('data.questions.0.options.0'));

        // إرسال كل الإجابات صحيحة دفعة واحدة.
        $answers = [];
        foreach ($examOptions as $questionId => $optionId) {
            $answers[] = ['exam_question_id' => $questionId, 'exam_question_option_id' => $optionId];
        }
        $submit = $this->postJson("/api/courses/{$this->course->id}/exam/submit", ['answers' => $answers])->assertOk();

        // دروس 100% وامتحان 100% => النهائي 100.
        $submit->assertJsonPath('data.result.final_score', '100.00')
            ->assertJsonPath('data.result.exam_percentage', '100.00')
            ->assertJsonPath('data.result.lessons_percentage', '100.00');

        // محاولة ثانية مرفوضة.
        $this->postJson("/api/courses/{$this->course->id}/exam/start")->assertStatus(409);
    }

    public function test_unenrolled_student_cannot_start_exam(): void
    {
        $student = $this->student();
        Sanctum::actingAs($student);

        $this->postJson("/api/courses/{$this->course->id}/exam/start")->assertStatus(403);
    }
}
