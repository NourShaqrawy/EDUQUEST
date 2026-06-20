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
    public function __construct(private readonly NotificationService $notifications) {}

    /** أوزان التقييم النهائي. */
    public const LESSONS_WEIGHT = 0.30;

    public const EXAM_WEIGHT = 0.70;

    /** عتبة اجتياز الكورس (للإشعارات). */
    public const PASS_THRESHOLD = 50.0;

    /** الحد الأدنى للدرجة النهائية للحصول على شهادة. */
    public const CERTIFICATE_THRESHOLD = 60.0;

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

    /** نسبة امتحان الكورس (مكوّن الـ 70%): الإجابات الصحيحة ÷ عدد أسئلة المحاولة × 100.
     *  الأسئلة غير المُجاب عنها تُحسب خاطئة (نقاطها صفر).
     */
    public function examPercentage(ExamAttempt $attempt): float
    {
        // عدد الأسئلة المثبّتة على هذه المحاولة (المسحوبة عشوائياً عند البدء).
        // إذا لم تكن مثبّتة (بيانات قديمة قبل الميزة)، نرجع لعدد أسئلة الامتحان.
        $totalQuestions = $attempt->attemptQuestions()->count()
            ?: $attempt->exam->questions()->count();

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

        $certificate = $this->issueCertificateIfEligible($user, $course, $finalScore);

        $this->notifyResult($user, $course, $finalScore, $certificate);

        return $result;
    }

    private function issueCertificateIfEligible(User $user, Course $course, float $finalScore): ?CourseCertificate
    {
        $level = $this->levelFor($finalScore);

        if ($level === null) {
            return null; // أقل من عتبة النجاح: لا شهادة (يبقى التقييم محفوظاً)
        }

        return CourseCertificate::firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'certificate_code' => 'CERT-'.$course->id.'-'.$user->id.'-'.strtoupper(Str::random(6)),
                'level' => $level,
                'issued_at' => now(),
            ]
        );
    }

    /** إشعار الطالب بنتيجته، وبشهادته إن صدرت للتوّ. */
    private function notifyResult(User $user, Course $course, float $finalScore, ?CourseCertificate $certificate): void
    {
        $passed = $finalScore >= self::PASS_THRESHOLD;

        $this->notifications->send(
            $user->id,
            $passed ? 'لقد اجتزت الامتحان 🎉' : 'لم تجتز الامتحان',
            "نتيجتك النهائية في كورس \"{$course->title}\": {$finalScore}%.",
            'exam_result',
            ['course_id' => $course->id, 'final_score' => $finalScore, 'passed' => $passed],
        );

        // شهادة صدرت لأول مرة في هذه المحاولة فقط (تجنّباً للتكرار)
        if ($certificate && $certificate->wasRecentlyCreated) {
            $this->notifications->send(
                $user->id,
                'تهانينا! حصلت على شهادة 🏆',
                "حصلت على شهادة بمستوى \"{$certificate->level}\" في كورس \"{$course->title}\".",
                'certificate',
                [
                    'course_id' => $course->id,
                    'certificate_code' => $certificate->certificate_code,
                    'level' => $certificate->level,
                ],
            );
        }
    }

    /** تعيين مستوى الشهادة من الدرجة النهائية. */
    private function levelFor(float $finalScore): ?string
    {
        return match (true) {
            $finalScore >= 85 => 'excellent',
            $finalScore >= 70 => 'good',
            $finalScore >= self::CERTIFICATE_THRESHOLD => 'average',
            default => null,
        };
    }
}
