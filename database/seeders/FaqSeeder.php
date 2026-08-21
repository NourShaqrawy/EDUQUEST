<?php

namespace Database\Seeders;

use App\Http\Controllers\FaqController;
use App\Models\AppSetting;
use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * الأسئلة الأربعة الافتراضية (نفس ما كان معروضاً في الصفحة الرئيسية) — منشورة ودائمة في قاعدة البيانات.
     * idempotent: لا تُكرَّر إن وُجدت أسئلة مسبقاً.
     */
    public function run(): void
    {
        // إعداد عدد العرض الافتراضي
        if (! AppSetting::query()->whereKey(FaqController::DISPLAY_COUNT_KEY)->exists()) {
            AppSetting::put(FaqController::DISPLAY_COUNT_KEY, FaqController::DISPLAY_DEFAULT);
        }

        if (Faq::query()->exists()) {
            return; // أسئلة موجودة مسبقاً — لا نعبث بها
        }

        $defaults = [
            [
                'question_en' => 'What fields of courses are available on the platform?',
                'question_ar' => 'ما هي مجالات الكورسات المتاحة على المنصة؟',
                'answer_en'   => 'The platform offers courses in various fields such as programming, design, digital marketing, personal development, and more. You can browse and benefit from all courses for free.',
                'answer_ar'   => 'توفر المنصة كورسات في عدة مجالات، مثل البرمجة، التصميم، التسويق الرقمي، تطوير الذات، وغيرها الكثير. يمكنك تصفح جميع الكورسات مجانًا والاستفادة من محتواها.',
            ],
            [
                'question_en' => 'How can I benefit from embedded exercises in the video?',
                'question_ar' => 'كيف يمكنني الاستفادة من التمارين المدمجة في الفيديو؟',
                'answer_en'   => 'While watching lessons, interactive exercises will appear to help you test your understanding of the content instantly.',
                'answer_ar'   => 'أثناء مشاهدة الدروس، ستظهر لك تمارين تفاعلية تساعدك في اختبار فهمك للمحتوى مباشرةً.',
            ],
            [
                'question_en' => 'Are there certificates after completing courses?',
                'question_ar' => 'هل هناك شهادات بعد إكمال الكورسات؟',
                'answer_en'   => 'Yes! After completing all lessons and passing the final exam with a score of 60% or above, you receive a personalised certificate that you can download as a PDF directly from the platform.',
                'answer_ar'   => 'نعم! بعد إتمام جميع الدروس واجتياز الامتحان النهائي بنتيجة 60% أو أعلى، ستحصل على شهادة باسمك يمكنك تحميلها بصيغة PDF مباشرةً من المنصة.',
            ],
            [
                'question_en' => 'How can I search for a specific course?',
                'question_ar' => 'كيف يمكنني البحث عن كورس معين؟',
                'answer_en'   => 'You can use the search feature within the website to find courses by field or course title.',
                'answer_ar'   => 'يمكنك استخدام خاصية البحث داخل الموقع للعثور على الكورسات حسب اسم المجال أو عنوان الكورس.',
            ],
        ];

        foreach ($defaults as $i => $row) {
            Faq::create(array_merge($row, [
                'is_published' => true,
                'sort_order'   => $i + 1,
            ]));
        }
    }
}
