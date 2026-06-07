<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseCertificate;
use App\Models\CourseResult;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Models\VideoQuestion;
use App\Models\VideoQuestionAnswer;
use Illuminate\Support\Str;

class ExamGradingService
{
    /** أوزان التقييم النهائي. */
    public const LESSONS_WEIGHT = 0.30;

    public const EXAM_WEIGHT = 0.70;

    /** عتبة الحصول على شهادة (ودرجاتها). */
    public const PASS_THRESHOLD = 50.0;

    /**
     * نسبة أسئلة الدروس (مكوّن الـ 30%): مجموع الإجابات الصحيحة على كل أسئلة الكورس
     * ÷ مجموع كل أسئلة الدروس × 100. كورس بلا أسئلة دروس = 100% (لا شيء لخسارته).
     */
    public function lessonsPercentage(User $user, Course $course): float
    {
        $videoIds = $course->courseVideos()->pluck('id');
        $totalQuestions = VideoQuestion::whereIn('video_id', $videoIds)->count();

        if ($totalQuestions === 0) {
            return 100.0;
        }

        $correct = VideoQuestionAnswer::where('user_id', $user->id)
            ->whereIn('question_id', VideoQuestion::whereIn('video_id', $videoIds)->select('id'))
            ->where('is_correct', true)
            ->count();

        return round($correct / $totalQuestions * 100, 2);
    }

    /** نسبة امتحان الكورس (مكوّن الـ 70%): الإجابات الصحيحة ÷ عدد الأسئلة × 100. */
    public function examPercentage(ExamAttempt $attempt): float
    {
        $totalQuestions = $attempt->exam->questions()->count();

        if ($totalQuestions === 0) {
            return 0.0;
        }

        $correct = $attempt->answers()->where('is_correct', true)->count();

        return round($correct / $totalQuestions * 100, 2);
    }

    /**
     * يحسب التقييم النهائي ويحفظه، ويُصدر الشهادة عند تجاوز العتبة. idempotent عبر updateOrCreate.
     */
    public function recordFinalResult(ExamAttempt $attempt, float $examPercentage): CourseResult
    {
        $user = $attempt->user;
        $course = $attempt->exam->course;

        $lessonsPercentage = $this->lessonsPercentage($user, $course);
        $finalScore = round(
            $lessonsPercentage * self::LESSONS_WEIGHT + $examPercentage * self::EXAM_WEIGHT,
            2
        );

        $result = CourseResult::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'lessons_percentage' => $lessonsPercentage,
                'exam_percentage' => $examPercentage,
                'final_score' => $finalScore,
            ]
        );

        $this->issueCertificateIfEligible($user, $course, $finalScore);

        return $result;
    }

    private function issueCertificateIfEligible(User $user, Course $course, float $finalScore): void
    {
        $level = $this->levelFor($finalScore);

        if ($level === null) {
            return; // أقل من عتبة النجاح: لا شهادة (يبقى التقييم محفوظاً)
        }

        CourseCertificate::firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'certificate_code' => 'CERT-'.$course->id.'-'.$user->id.'-'.strtoupper(Str::random(6)),
                'level' => $level,
                'issued_at' => now(),
            ]
        );
    }

    /** تعيين مستوى الشهادة من الدرجة النهائية (عدّل العتبات حسب سياستك). */
    private function levelFor(float $finalScore): ?string
    {
        return match (true) {
            $finalScore >= 85 => 'excellent',
            $finalScore >= 70 => 'good',
            $finalScore >= self::PASS_THRESHOLD => 'average',
            default => null,
        };
    }
}
