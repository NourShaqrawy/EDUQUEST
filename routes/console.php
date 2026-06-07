<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// بثّ عدّاد الدقائق لمحاولات الامتحان الجارية وإنهاء المنتهية مدّتها (شبكة أمان).
Schedule::command('exam:broadcast-ticks')->everyMinute();
