<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Course;
use App\Models\CourseVideo;
use App\Models\LessonProgress;
use App\Models\User;
use App\Models\VideoQuestion;
use App\Models\VideoQuestionAnswer;

class LessonProgressService
{
    /** تسجيل مشاهدة درس. الدرس يجب أن يكون متاحاً للطالب (تسجيل + فتح تسلسلي). */
    public function markWatched(User $user, CourseVideo $video): LessonProgress
    {
        if (! $video->isAccessibleFor($user)) {
            throw ApiException::forbidden('هذا الدرس غير متاح لك بعد.');
        }

        return LessonProgress::firstOrCreate(
            ['user_id' => $user->id, 'course_video_id' => $video->id],
            ['watched_at' => now()],
        );
    }

    /**
     * شرط دخول الامتحان: الإجابة على جميع أسئلة الدروس (المشاهدة ليست مطلوبة).
     */
    public function hasCompletedAllLessons(User $user, Course $course): bool
    {
        $videoIds = $course->courseVideos()->pluck('id');

        if ($videoIds->isEmpty()) {
            return false; // كورس بلا دروس لا يُتاح له امتحان
        }

        $totalQuestions = VideoQuestion::whereIn('video_id', $videoIds)->count();

        if ($totalQuestions === 0) {
            return true;
        }

        $answeredCount = VideoQuestionAnswer::where('user_id', $user->id)
            ->whereIn('question_id', VideoQuestion::whereIn('video_id', $videoIds)->select('id'))
            ->count();

        return $answeredCount >= $totalQuestions;
    }

    /** ملخّص تقدّم الطالب في الكورس (لعرضه في واجهة الطالب). */
    public function progressSummary(User $user, Course $course): array
    {
        $videoIds = $course->courseVideos()->pluck('id');
        $totalVideos = $videoIds->count();

        $watchedVideos = LessonProgress::where('user_id', $user->id)
            ->whereIn('course_video_id', $videoIds)
            ->count();

        $totalQuestions = VideoQuestion::whereIn('video_id', $videoIds)->count();
        $answeredQuestions = $totalQuestions === 0 ? 0 : VideoQuestionAnswer::where('user_id', $user->id)
            ->whereIn('question_id', VideoQuestion::whereIn('video_id', $videoIds)->select('id'))
            ->count();

        return [
            'total_videos' => $totalVideos,
            'watched_videos' => $watchedVideos,
            'total_lesson_questions' => $totalQuestions,
            'answered_lesson_questions' => $answeredQuestions,
            'lessons_completed' => $this->hasCompletedAllLessons($user, $course),
        ];
    }
}
