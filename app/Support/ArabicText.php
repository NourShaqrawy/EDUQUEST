<?php

namespace App\Support;

/**
 * تحويل النص العربي المنطقي إلى نص جاهز للطباعة في DomPDF.
 *
 * DomPDF لا يدعم العربية: لا يصل الحروف (shaping) ولا يعكس اتجاه الكتابة (bidi)،
 * فيخرج الاسم "عمر الشامي" مقطّعاً ومعكوساً. هذه الفئة تقوم بالأمرين يدوياً:
 *
 *   1. Shaping — استبدال كل حرف عربي بشكله السياقي (مبدئي/وسطي/نهائي/منفرد)
 *      من كتلة Arabic Presentation Forms-B (U+FE70–U+FEFF)، مع دمج
 *      اللام+ألف في الرباط (ligature) الخاص بها.
 *   2. Bidi — عكس ترتيب المقاطع العربية داخل النص مع إبقاء المقاطع
 *      اللاتينية والأرقام بترتيبها الطبيعي، وقلب الأقواس المتناظرة.
 *
 * الخط المستخدم في القالب (DejaVu Sans) يحتوي على هذه الأشكال، لذلك
 * لا حاجة لتثبيت خط إضافي.
 */
final class ArabicText
{
    /**
     * جدول الأشكال السياقية لكل حرف عربي.
     * المفتاح = الحرف الأصلي، القيمة = [منفرد، نهائي، وسطي، مبدئي].
     * القيمة null تعني أن الحرف لا يملك هذا الشكل (حرف لا يوصل ما بعده).
     */
    private const FORMS = [
        'ء' => ['ﺀ', null, null, null],
        'آ' => ['ﺁ', 'ﺂ', null, null],
        'أ' => ['ﺃ', 'ﺄ', null, null],
        'ؤ' => ['ﺅ', 'ﺆ', null, null],
        'إ' => ['ﺇ', 'ﺈ', null, null],
        'ئ' => ['ﺉ', 'ﺊ', 'ﺋ', 'ﺌ'],
        'ا' => ['ﺍ', 'ﺎ', null, null],
        'ب' => ['ﺏ', 'ﺐ', 'ﺒ', 'ﺑ'],
        'ة' => ['ﺓ', 'ﺔ', null, null],
        'ت' => ['ﺕ', 'ﺖ', 'ﺘ', 'ﺗ'],
        'ث' => ['ﺙ', 'ﺚ', 'ﺜ', 'ﺛ'],
        'ج' => ['ﺝ', 'ﺞ', 'ﺠ', 'ﺟ'],
        'ح' => ['ﺡ', 'ﺢ', 'ﺤ', 'ﺣ'],
        'خ' => ['ﺥ', 'ﺦ', 'ﺨ', 'ﺧ'],
        'د' => ['ﺩ', 'ﺪ', null, null],
        'ذ' => ['ﺫ', 'ﺬ', null, null],
        'ر' => ['ﺭ', 'ﺮ', null, null],
        'ز' => ['ﺯ', 'ﺰ', null, null],
        'س' => ['ﺱ', 'ﺲ', 'ﺴ', 'ﺳ'],
        'ش' => ['ﺵ', 'ﺶ', 'ﺸ', 'ﺷ'],
        'ص' => ['ﺹ', 'ﺺ', 'ﺼ', 'ﺻ'],
        'ض' => ['ﺽ', 'ﺾ', 'ﻀ', 'ﺿ'],
        'ط' => ['ﻁ', 'ﻂ', 'ﻄ', 'ﻃ'],
        'ظ' => ['ﻅ', 'ﻆ', 'ﻈ', 'ﻇ'],
        'ع' => ['ﻉ', 'ﻊ', 'ﻌ', 'ﻋ'],
        'غ' => ['ﻍ', 'ﻎ', 'ﻐ', 'ﻏ'],
        'ف' => ['ﻑ', 'ﻒ', 'ﻔ', 'ﻓ'],
        'ق' => ['ﻕ', 'ﻖ', 'ﻘ', 'ﻗ'],
        'ك' => ['ﻙ', 'ﻚ', 'ﻜ', 'ﻛ'],
        'ل' => ['ﻝ', 'ﻞ', 'ﻠ', 'ﻟ'],
        'م' => ['ﻡ', 'ﻢ', 'ﻤ', 'ﻣ'],
        'ن' => ['ﻥ', 'ﻦ', 'ﻨ', 'ﻧ'],
        'ه' => ['ﻩ', 'ﻪ', 'ﻬ', 'ﻫ'],
        'و' => ['ﻭ', 'ﻮ', null, null],
        'ى' => ['ﻯ', 'ﻰ', null, null],
        'ي' => ['ﻱ', 'ﻲ', 'ﻴ', 'ﻳ'],
        'پ' => ['ﭖ', 'ﭗ', 'ﭙ', 'ﭘ'],
        'چ' => ['ﭺ', 'ﭻ', 'ﭽ', 'ﭼ'],
        'ژ' => ['ﮊ', 'ﮋ', null, null],
        'ک' => ['ﮎ', 'ﮏ', 'ﮑ', 'ﮐ'],
        'گ' => ['ﮒ', 'ﮓ', 'ﮕ', 'ﮔ'],
        'ی' => ['ﯼ', 'ﯽ', 'ﯿ', 'ﯾ'],
    ];

    /** روابط لام + ألف: المفتاح = الألف التالية، القيمة = [منفرد، نهائي]. */
    private const LAM_ALEF = [
        'آ' => ['ﻵ', 'ﻶ'],
        'أ' => ['ﻷ', 'ﻸ'],
        'إ' => ['ﻹ', 'ﻺ'],
        'ا' => ['ﻻ', 'ﻼ'],
    ];

    /** أقواس وعلامات يجب قلبها عند عكس اتجاه المقطع. */
    private const MIRRORED = [
        '(' => ')', ')' => '(',
        '[' => ']', ']' => '[',
        '{' => '}', '}' => '{',
        '<' => '>', '>' => '<',
        '«' => '»', '»' => '«',
    ];

    /** التشكيل والعلامات التي لا تكسر الوصل بين الحروف. */
    private const DIACRITICS = [
        'ً', 'ٌ', 'ٍ', 'َ', 'ُ', 'ِ', 'ّ', 'ْ', 'ٓ', 'ٰ', 'ٔ', 'ٕ',
    ];

    /**
     * هل يحتوي النص على حرف عربي؟ تُستخدم لتفادي أي معالجة للنصوص اللاتينية.
     */
    public static function hasArabic(?string $text): bool
    {
        return $text !== null && preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u', $text) === 1;
    }

    /**
     * النقطة الوحيدة التي يستدعيها القالب: تُرجع النص جاهزاً للطباعة.
     * النصوص التي لا تحتوي عربية تُرجع كما هي دون أي تغيير.
     */
    public static function forPdf(?string $text): string
    {
        $text = (string) $text;

        if ($text === '' || ! self::hasArabic($text)) {
            return $text;
        }

        return self::reorder(self::shape($text));
    }

    // ─── Shaping ────────────────────────────────────────────────────────────────

    /** يستبدل كل حرف عربي بشكله السياقي ويدمج روابط لام+ألف. */
    private static function shape(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $out   = [];
        $count = count($chars);

        for ($i = 0; $i < $count; $i++) {
            $char = $chars[$i];

            if (! isset(self::FORMS[$char])) {
                $out[] = $char;

                continue;
            }

            $prev = self::neighbour($chars, $i, -1);
            $next = self::neighbour($chars, $i, +1);

            // رابط لام + ألف يُكتب كحرف واحد، فنستهلك الألف التالية.
            if ($char === 'ل' && $next !== null && isset(self::LAM_ALEF[$next])) {
                $ligature = self::LAM_ALEF[$next];
                $out[]    = self::joinsToPrevious($prev) ? $ligature[1] : $ligature[0];
                $i        = self::skipTo($chars, $i, $next);

                continue;
            }

            $connectsBefore = self::joinsToPrevious($prev);
            $connectsAfter  = $next !== null && isset(self::FORMS[$next]);

            $forms = self::FORMS[$char];
            $form  = match (true) {
                $connectsBefore && $connectsAfter => $forms[2] ?? $forms[1],   // وسطي
                $connectsBefore                   => $forms[1],               // نهائي
                $connectsAfter                    => $forms[3] ?? $forms[0],  // مبدئي
                default                           => $forms[0],               // منفرد
            };

            $out[] = $form ?? $forms[0];
        }

        return implode('', $out);
    }

    /**
     * أقرب حرف غير تشكيلي في الاتجاه المحدد، أو null إن لم يوجد.
     */
    private static function neighbour(array $chars, int $index, int $step): ?string
    {
        $count = count($chars);

        for ($i = $index + $step; $i >= 0 && $i < $count; $i += $step) {
            if (! in_array($chars[$i], self::DIACRITICS, true)) {
                return $chars[$i];
            }
        }

        return null;
    }

    /** هل يصل الحرف السابق بما بعده؟ (الحروف ذات الشكل المبدئي فقط) */
    private static function joinsToPrevious(?string $prev): bool
    {
        return $prev !== null
            && isset(self::FORMS[$prev])
            && self::FORMS[$prev][3] !== null;
    }

    /** يتقدّم بالمؤشّر حتى الحرف المستهلَك (متجاوزاً التشكيل بينهما). */
    private static function skipTo(array $chars, int $index, string $target): int
    {
        $count = count($chars);

        for ($i = $index + 1; $i < $count; $i++) {
            if ($chars[$i] === $target) {
                return $i;
            }
        }

        return $index;
    }

    // ─── Bidi ───────────────────────────────────────────────────────────────────

    /**
     * يعكس ترتيب المقاطع العربية ويُبقي المقاطع اللاتينية/الرقمية كما هي،
     * ثم يعكس ترتيب المقاطع نفسها لأن اتجاه الفقرة من اليمين لليسار.
     */
    private static function reorder(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        // 1. تصنيف كل حرف: rtl / ltr / محايد (null).
        $levels = array_map(static fn (string $c): ?bool => match (true) {
            self::isRtlChar($c) => true,
            self::isNeutral($c) => null,
            default             => false,
        }, $chars);

        // 2. حسم المحايدات: المحايد يأخذ اتجاه جيرانه إن اتفقا،
        //    وإلا يأخذ اتجاه الفقرة (RTL). هذا يمنع التصاق المسافات
        //    والأقواس بالمقطع اللاتيني وقلبها خطأً.
        $count = count($levels);
        for ($i = 0; $i < $count; $i++) {
            if ($levels[$i] !== null) {
                continue;
            }

            $end = $i;
            while ($end + 1 < $count && $levels[$end + 1] === null) {
                $end++;
            }

            $before   = $i > 0 ? $levels[$i - 1] : true;
            $after    = $end + 1 < $count ? $levels[$end + 1] : true;
            $resolved = $before === $after ? $before : true;

            for ($j = $i; $j <= $end; $j++) {
                $levels[$j] = $resolved;
            }

            $i = $end;
        }

        // 3. تجميع الحروف في مقاطع متجانسة الاتجاه.
        $runs = [];
        foreach ($chars as $i => $char) {
            $rtl = $levels[$i];

            if ($runs !== [] && $runs[count($runs) - 1]['rtl'] === $rtl) {
                $runs[count($runs) - 1]['text'] .= $char;

                continue;
            }

            $runs[] = ['rtl' => $rtl, 'text' => $char];
        }

        $pieces = [];
        foreach ($runs as $run) {
            $pieces[] = $run['rtl'] ? self::reverseRun($run['text']) : $run['text'];
        }

        // اتجاه الفقرة RTL: المقطع الأول منطقياً يُطبع في أقصى اليمين.
        return implode('', array_reverse($pieces));
    }

    /** يعكس حروف مقطع عربي ويقلب الأقواس المتناظرة داخله. */
    private static function reverseRun(string $run): string
    {
        $chars = preg_split('//u', $run, -1, PREG_SPLIT_NO_EMPTY);
        $chars = array_reverse($chars);

        foreach ($chars as $i => $char) {
            if (isset(self::MIRRORED[$char])) {
                $chars[$i] = self::MIRRORED[$char];
            }
        }

        return implode('', $chars);
    }

    /** حرف عربي أصلي أو أحد أشكاله السياقية. */
    private static function isRtlChar(string $char): bool
    {
        return preg_match(
            '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u',
            $char
        ) === 1;
    }

    /** مسافات وعلامات ترقيم لا تحدّد اتجاهاً بنفسها. */
    private static function isNeutral(string $char): bool
    {
        return preg_match('/[\s\x{0640}!-\/:-@\[-`{-~«»]/u', $char) === 1;
    }
}
