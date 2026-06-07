<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoProgressionTest extends TestCase
{
    use RefreshDatabase;

    private int $video1Id;

    private int $video2Id;

    private int $q1Id;

    private int $q1CorrectOptionId;

    private int $q2Id;

    private int $q2CorrectOptionId;

    private int $courseId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCourseWithTwoVideos();
    }

    /** ينشئ كورساً فيه فيديوهان متتاليان، لكل منهما سؤال واحد بخيارين. */
    private function seedCourseWithTwoVideos(): void
    {
        $publisher = User::create([
            'name' => 'Pub', 'email' => 'pub@test.com',
            'role' => 'publisher', 'password' => bcrypt('password123'),
        ]);

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Cat', 'description' => 'd', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->courseId = DB::table('courses')->insertGetId([
            'title' => 'C', 'description' => 'd', 'thumbnail' => 'x.jpg',
            'category_id' => $categoryId, 'publisher_id' => $publisher->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        [$this->video1Id, $this->q1Id, $this->q1CorrectOptionId] = $this->makeVideoWithQuestion('V1', 1);
        [$this->video2Id, $this->q2Id, $this->q2CorrectOptionId] = $this->makeVideoWithQuestion('V2', 2);
    }

    private function makeVideoWithQuestion(string $title, int $order): array
    {
        $videoId = DB::table('course_videos')->insertGetId([
            'course_id' => $this->courseId, 'title' => $title, 'description' => 'd',
            'video_path' => "path/$title.mp4", 'duration' => 100, 'order' => $order,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $questionId = DB::table('video_questions')->insertGetId([
            'video_id' => $videoId, 'question' => "Q for $title", 'time_in_video' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $correctId = DB::table('video_question_options')->insertGetId([
            'question_id' => $questionId, 'option_text' => 'right', 'is_correct' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('video_question_options')->insert([
            'question_id' => $questionId, 'option_text' => 'wrong', 'is_correct' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$videoId, $questionId, $correctId];
    }

    private function actingAsStudent(): User
    {
        $student = User::create([
            'name' => 'Stud', 'email' => 'stud@test.com',
            'role' => 'user', 'password' => bcrypt('password123'),
        ]);
        Sanctum::actingAs($student);

        return $student;
    }

    public function test_first_video_unlocked_second_locked_with_hidden_urls(): void
    {
        $this->actingAsStudent();

        $res = $this->getJson("/api/courses/{$this->courseId}/lessons")->assertOk();

        $videos = $res->json('data.videos');
        $this->assertTrue($videos[0]['can_watch']);
        $this->assertFalse($videos[0]['is_completed']);   // فيه سؤال غير مُجاب
        $this->assertFalse($videos[1]['can_watch']);      // الفيديو الثاني مقفل

        // روابط الفيديو المقفل مخفية، والمفتوح ظاهرة
        $this->assertArrayHasKey('video_url', $videos[0]);
        $this->assertArrayNotHasKey('video_url', $videos[1]);
        $this->assertArrayNotHasKey('video_path', $videos[1]);
    }

    public function test_locked_video_questions_return_403(): void
    {
        $this->actingAsStudent();

        $this->getJson("/api/videos/{$this->video2Id}/questions")->assertStatus(403);
        $this->getJson("/api/videos/{$this->video1Id}/questions")->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cannot_answer_locked_video_question(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/video-answers', [
            'question_id' => $this->q2Id,
            'option_id' => $this->q2CorrectOptionId,
        ])->assertStatus(403);
    }

    public function test_answering_all_questions_unlocks_next_video(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/video-answers', [
            'question_id' => $this->q1Id,
            'option_id' => $this->q1CorrectOptionId,
        ])->assertCreated()->assertJsonPath('is_correct', true);

        // الفيديو الأول صار مكتملاً والثاني مفتوحاً
        $videos = $this->getJson("/api/courses/{$this->courseId}/lessons")->json('data.videos');
        $this->assertTrue($videos[0]['is_completed']);
        $this->assertTrue($videos[1]['can_watch']);

        // الآن يمكن جلب أسئلة الفيديو الثاني
        $this->getJson("/api/videos/{$this->video2Id}/questions")->assertOk();
    }

    public function test_answered_question_is_hidden_and_cannot_be_reanswered(): void
    {
        $this->actingAsStudent();

        $this->postJson('/api/video-answers', [
            'question_id' => $this->q1Id,
            'option_id' => $this->q1CorrectOptionId,
        ])->assertCreated();

        // السؤال المُجاب لا يظهر مرة أخرى
        $this->getJson("/api/videos/{$this->video1Id}/questions")->assertOk()
            ->assertJsonCount(0, 'data');

        // إعادة الإجابة مرفوضة (409)
        $this->postJson('/api/video-answers', [
            'question_id' => $this->q1Id,
            'option_id' => $this->q1CorrectOptionId,
        ])->assertStatus(409);
    }
}
