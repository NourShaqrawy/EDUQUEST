<?php

namespace App\Jobs;

use App\Events\ExamTimeUp;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * شبكة الأمان: يُنهي المحاولة تلقائياً عند انقضاء المدة حتى لو لم يُرسل الطالب
 * أو انقطع اتصاله. يُجدول لحظة البدء بتأخير يساوي ends_at. idempotent.
 */
class FinalizeExamAttempt implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $attemptId) {}

    public function handle(ExamService $exams): void
    {
        $attempt = ExamAttempt::find($this->attemptId);

        if (! $attempt || $attempt->isFinalized() || ! $attempt->hasTimeExpired()) {
            return;
        }

        $finalized = $exams->finalize($attempt, ExamAttempt::STATUS_EXPIRED);

        ExamTimeUp::dispatch($finalized->id, $finalized->status);
    }
}
