<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * يفحص ملفات mp4 المخزّنة ويعيد تغليف غير المهيّأة منها بـ faststart
 * (نقل ذرّة moov إلى بداية الملف) دون إعادة ترميز — يحافظ على الجودة والحجم.
 *
 * لازم للملفات المولَّدة قبل إضافة -movflags +faststart إلى أوامر FFmpeg:
 * بدون faststart يضطر المتصفح لتحميل الملف كاملاً قبل أن يتمكّن من التحريك.
 */
class VideosFaststart extends Command
{
    protected $signature = 'videos:faststart
                            {--dry-run : اعرض الملفات التي ستُعالَج دون تعديلها}
                            {--path=course-videos : المجلد النسبي داخل قرص public}';

    protected $description = 'إعادة تغليف ملفات mp4 القديمة بـ faststart ليعمل التحريك وتبديل الجودة';

    public function handle(): int
    {
        $root = storage_path('app/public/'.trim($this->option('path'), '/'));

        if (! is_dir($root)) {
            $this->error("المجلد غير موجود: {$root}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $files = $this->findMp4Files($root);

        $this->info(sprintf('تم العثور على %d ملف mp4.', count($files)));

        $converted = $skipped = $failed = 0;

        foreach ($files as $file) {
            if ($this->hasFaststart($file)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line('  [سيُعالَج] '.$this->relative($file, $root));
                $converted++;

                continue;
            }

            if ($this->remux($file)) {
                $this->line('  <info>[تم]</info> '.$this->relative($file, $root));
                $converted++;
            } else {
                $this->line('  <error>[فشل]</error> '.$this->relative($file, $root));
                $failed++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d، متجاوَز (مهيّأ مسبقاً): %d، فاشل: %d',
            $dryRun ? 'سيُعالَج' : 'تمت المعالجة',
            $converted, $skipped, $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return string[] */
    private function findMp4Files(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && strtolower($item->getExtension()) === 'mp4') {
                $files[] = $item->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * هل ذرّة moov تسبق ذرّة mdat؟ نمشي على ذرّات المستوى الأعلى فقط:
     * كل ذرّة = 4 بايت للحجم (big-endian) + 4 بايت للنوع.
     * حجم 1 يعني حجماً 64-بت في الـ 8 بايت التالية، وحجم 0 يعني "حتى نهاية الملف".
     *
     * الاعتماد على بنية الملف (لا على إزاحة تقريبية) هو ما يجعل الأمر
     * idempotent: إعادة تشغيله تتجاوز كل ما عولج سابقاً.
     */
    private function hasFaststart(string $file): bool
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return false;
        }

        try {
            $offset = 0;
            $size = filesize($file);

            while ($offset < $size - 8) {
                fseek($handle, $offset);
                $header = fread($handle, 8);
                if ($header === false || strlen($header) < 8) {
                    return false;
                }

                $boxSize = unpack('N', substr($header, 0, 4))[1];
                $type = substr($header, 4, 4);

                if ($type === 'moov') {
                    return true;   // moov قبل mdat → مهيّأ
                }
                if ($type === 'mdat') {
                    return false;  // mdat أولاً → غير مهيّأ
                }

                if ($boxSize === 1) {
                    $ext = fread($handle, 8);
                    if ($ext === false || strlen($ext) < 8) {
                        return false;
                    }
                    $parts = unpack('N2', $ext);
                    $boxSize = ($parts[1] << 32) + $parts[2];
                } elseif ($boxSize === 0) {
                    return false;  // يمتد حتى النهاية ولم نجد moov
                }

                if ($boxSize < 8) {
                    return false;  // ملف تالف — عالجه احتياطاً
                }

                $offset += $boxSize;
            }
        } finally {
            fclose($handle);
        }

        return false;
    }

    /** إعادة التغليف مع نسخة .bak وتراجع عند الفشل. */
    private function remux(string $file): bool
    {
        $temp = $file.'.faststart.tmp.mp4';

        $process = new Process([
            'ffmpeg', '-v', 'error', '-y', '-i', $file,
            '-c', 'copy', '-movflags', '+faststart', $temp,
        ]);
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($temp) || filesize($temp) === 0) {
            @unlink($temp);

            return false;
        }

        $backup = $file.'.bak';
        if (! @rename($file, $backup)) {
            @unlink($temp);

            return false;
        }
        if (! @rename($temp, $file)) {
            @rename($backup, $file); // تراجع
            @unlink($temp);

            return false;
        }
        @unlink($backup);

        return true;
    }

    private function relative(string $file, string $root): string
    {
        return ltrim(str_replace([$root, '\\'], ['', '/'], $file), '/');
    }
}
