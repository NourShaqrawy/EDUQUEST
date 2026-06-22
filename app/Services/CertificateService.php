<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseCertificate;
use App\Models\CourseResult;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Str;

class CertificateService
{
    /**
     * Check eligibility and issue (or retrieve) the certificate for user+course.
     * Idempotent: returns the existing certificate if already issued.
     *
     * Eligibility rules:
     *  1. Student must have a finalized exam attempt (submitted or expired).
     *  2. Student's final_score must be >= CERTIFICATE_THRESHOLD (60%).
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  422 on ineligibility
     */
    public function requestCertificate(User $user, Course $course): CourseCertificate
    {
        $this->assertEligible($user, $course);

        $result = CourseResult::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        return CourseCertificate::firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            [
                'certificate_code' => 'CERT-'.$course->id.'-'.$user->id.'-'.strtoupper(Str::random(6)),
                'level'            => $this->levelFor((float) $result->final_score),
                'issued_at'        => now(),
            ]
        );
    }

    /** Returns the issued certificate for user+course, or null if not yet issued. */
    public function getCertificate(User $user, Course $course): ?CourseCertificate
    {
        return CourseCertificate::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();
    }

    /**
     * Returns eligibility status without throwing — useful for the show endpoint.
     *
     * @return array{eligible: bool, reason: string|null, final_score: float|null}
     */
    public function eligibilityStatus(User $user, Course $course): array
    {
        // الكورس التعريفي لا يمنح شهادة إطلاقاً.
        if (! $course->has_certificate) {
            return [
                'eligible'    => false,
                'reason'      => 'هذا الكورس تعريفي ولا يمنح شهادة.',
                'final_score' => null,
            ];
        }

        $attempt = $this->getFinalizedAttempt($user, $course);

        if (! $attempt) {
            return [
                'eligible'    => false,
                'reason'      => 'لم تقم باجتياز الامتحان النهائي بعد.',
                'final_score' => null,
            ];
        }

        $result = CourseResult::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (! $result) {
            return [
                'eligible'    => false,
                'reason'      => 'لا يوجد تقييم نهائي مسجّل لك في هذا الكورس.',
                'final_score' => null,
            ];
        }

        $score = (float) $result->final_score;

        if ($score < ExamGradingService::CERTIFICATE_THRESHOLD) {
            return [
                'eligible'    => false,
                'reason'      => sprintf(
                    'معدلك الكلي (%.1f%%) أقل من الحد الأدنى للحصول على الشهادة (%.0f%%).',
                    $score,
                    ExamGradingService::CERTIFICATE_THRESHOLD
                ),
                'final_score' => $score,
            ];
        }

        return ['eligible' => true, 'reason' => null, 'final_score' => $score];
    }

    // ─── Private helpers ────────────────────────────────────────────────────────

    private function assertEligible(User $user, Course $course): void
    {
        $status = $this->eligibilityStatus($user, $course);

        if (! $status['eligible']) {
            abort(422, $status['reason']);
        }
    }

    private function getFinalizedAttempt(User $user, Course $course): ?ExamAttempt
    {
        $exam = $course->exam;

        if (! $exam) {
            return null;
        }

        return ExamAttempt::where('user_id', $user->id)
            ->where('course_exam_id', $exam->id)
            ->whereIn('status', [ExamAttempt::STATUS_SUBMITTED, ExamAttempt::STATUS_EXPIRED])
            ->latest()
            ->first();
    }

    private function levelFor(float $finalScore): string
    {
        return match (true) {
            $finalScore >= 85 => 'excellent',
            $finalScore >= 70 => 'good',
            default           => 'average',
        };
    }
}
