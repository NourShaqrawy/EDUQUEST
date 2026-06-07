<?php

namespace App\Console\Commands;

use App\Events\ExamTimerTick;
use App\Events\ExamTimeUp;
use App\Models\ExamAttempt;
use App\Services\ExamService;
use Illuminate\Console\Command;

/**
 * يُشغَّل كل دقيقة (عبر المجدول): يبثّ عدّاد الدقائق للمحاولات الجارية،
 * ويُنهي تلقائياً أي محاولة انقضت مدتها (شبكة أمان إضافية).
 * الدقيقة الأخيرة (عدّاد الثواني) تتولاها وظيفة ExamFinalCountdown.
 */
class BroadcastExamTicks extends Command
{
    protected $signature = 'exam:broadcast-ticks';

    protected $description = 'بثّ العدّ التنازلي لمحاولات الامتحان الجارية وإنهاء المنتهية مدّتها';

    public function handle(ExamService $exams): int
    {
        $attempts = ExamAttempt::where('status', ExamAttempt::STATUS_IN_PROGRESS)->get();

        foreach ($attempts as $attempt) {
            if ($attempt->hasTimeExpired()) {
                $finalized = $exams->finalize($attempt, ExamAttempt::STATUS_EXPIRED);
                ExamTimeUp::dispatch($finalized->id, $finalized->status);

                continue;
            }

            $remaining = $attempt->remainingSeconds();

            // الدقيقة الأخيرة لها عدّاد ثوانٍ مستقل، فنكتفي هنا بعدّاد الدقائق.
            if ($remaining > 60) {
                ExamTimerTick::dispatch($attempt->id, $remaining, 'running', 'minutes');
            }
        }

        return self::SUCCESS;
    }
}
