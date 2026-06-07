<?php

namespace App\Jobs;

use App\Events\ExamTimerTick;
use App\Events\ExamTimeUp;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * العدّ التنازلي بدقّة الثانية خلال الدقيقة الأخيرة من الامتحان.
 * يُجدول ليبدأ عند (ends_at - 60s)، ويبثّ نبضة كل ثانية، ثم يُنهي المحاولة عند الصفر.
 */
class ExamFinalCountdown implements ShouldQueue
{
    use Queueable;

    /** مهلة كافية للدقيقة الأخيرة مع هامش بسيط. */
    public int $timeout = 120;

    public function __construct(public int $attemptId) {}

    public function handle(ExamService $exams): void
    {
        // 70 تكرار يغطّي الدقيقة الأخيرة مع هامش لانحراف التوقيت.
        for ($i = 0; $i <= 70; $i++) {
            $attempt = ExamAttempt::find($this->attemptId);

            if (! $attempt || $attempt->isFinalized()) {
                return;
            }

            $remaining = $attempt->remainingSeconds();

            if ($remaining <= 0) {
                $finalized = $exams->finalize($attempt, ExamAttempt::STATUS_EXPIRED);
                ExamTimeUp::dispatch($finalized->id, $finalized->status);

                return;
            }

            ExamTimerTick::dispatch($attempt->id, $remaining, 'final_minute', 'seconds');

            sleep(1);
        }
    }
}
