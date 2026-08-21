<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Faq;
use App\Models\FaqSuggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaqController extends Controller
{
    /** الحد الأدنى المطلوب لعدد الأسئلة الشائعة الإجمالي. */
    public const MIN_TOTAL = 4;

    /** حدود عدد الأسئلة المعروضة في الصفحة الرئيسية. */
    public const DISPLAY_MIN = 2;
    public const DISPLAY_MAX = 5;
    public const DISPLAY_DEFAULT = 4;

    public const DISPLAY_COUNT_KEY = 'home_faq_count';

    /*
    |--------------------------------------------------------------------------
    | Public (🌐)
    |--------------------------------------------------------------------------
    */

    /**
     * الأسئلة المعروضة في الصفحة الرئيسية: المنشورة فقط، مرتّبة، بحد أقصى = عدد العرض المختار.
     */
    public function home()
    {
        $count = $this->displayCount();

        $faqs = Faq::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($count)
            ->get(['id', 'question_en', 'question_ar', 'answer_en', 'answer_ar']);

        return response()->json(['data' => $faqs]);
    }

    /**
     * الزائر يرسل اقتراح سؤال بلغة واحدة.
     */
    public function suggest(Request $request)
    {
        $data = $request->validate([
            'question' => 'required|string|min:5|max:1000',
        ]);

        FaqSuggestion::create([
            'question' => trim($data['question']),
            'status'   => 'pending',
        ]);

        return response()->json([
            'message' => 'تم إرسال سؤالك بنجاح، شكراً لمساهمتك.',
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin (👑) — إدارة الأسئلة الشائعة
    |--------------------------------------------------------------------------
    */

    /**
     * كل الأسئلة (منشورة وغير منشورة) لصفحة الإدارة، مع أعلام الاكتمال والقيود.
     */
    public function index()
    {
        $faqs = Faq::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Faq $f) => $this->present($f));

        return response()->json([
            'data' => $faqs,
            'meta' => [
                'total'            => Faq::count(),
                'published_count'  => Faq::where('is_published', true)->count(),
                'min_total'        => self::MIN_TOTAL,
                'display_count'    => $this->displayCount(),
                'display_min'      => self::DISPLAY_MIN,
                'display_max'      => self::DISPLAY_MAX,
            ],
        ]);
    }

    /**
     * إنشاء سؤال شائع. يجب أن يكون ثنائي اللغة كاملاً (سؤال + إجابة بالعربية والإنكليزية).
     * قد يُنشأ من اقتراح زائر (suggestion_id) فيُعلَّم الاقتراح كمحوّل.
     */
    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $faq = DB::transaction(function () use ($data, $request) {
            $faq = Faq::create([
                'question_en' => $data['question_en'],
                'question_ar' => $data['question_ar'],
                'answer_en'   => $data['answer_en'],
                'answer_ar'   => $data['answer_ar'],
                'is_published' => $data['is_published'] ?? false,
                'sort_order'  => $data['sort_order'] ?? (Faq::max('sort_order') + 1),
            ]);

            if ($request->filled('suggestion_id')) {
                FaqSuggestion::where('id', $request->input('suggestion_id'))
                    ->where('status', 'pending')
                    ->update(['status' => 'converted', 'faq_id' => $faq->id]);
            }

            return $faq;
        });

        return response()->json([
            'message' => 'تمت إضافة السؤال بنجاح.',
            'data'    => $this->present($faq),
        ], 201);
    }

    /**
     * تعديل سؤال شائع. عند إبقائه/جعله منشوراً يجب أن يبقى ثنائي اللغة كاملاً.
     */
    public function update(Request $request, $id)
    {
        $faq  = Faq::findOrFail($id);
        $data = $this->validatePayload($request);

        $faq->fill([
            'question_en' => $data['question_en'],
            'question_ar' => $data['question_ar'],
            'answer_en'   => $data['answer_en'],
            'answer_ar'   => $data['answer_ar'],
        ]);

        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $faq->sort_order = $data['sort_order'];
        }

        // النشر عبر هذا المسار متاح فقط لو كان مكتملاً (وهو مضمون بعد validatePayload)
        if (array_key_exists('is_published', $data)) {
            $faq->is_published = $data['is_published'];
        }

        $faq->save();

        return response()->json([
            'message' => 'تم تحديث السؤال بنجاح.',
            'data'    => $this->present($faq),
        ]);
    }

    /**
     * نشر / إلغاء نشر سؤال. لا يمكن النشر إلا لسؤال مكتمل ثنائي اللغة.
     * لا يمكن إلغاء النشر إذا كان سيُنقص المعروض تحت الحد الأدنى للعرض المختار.
     */
    public function togglePublish($id)
    {
        $faq = Faq::findOrFail($id);

        if (! $faq->is_published) {
            // نية النشر
            if (! $faq->isComplete()) {
                return response()->json([
                    'message' => 'لا يمكن عرض السؤال قبل توفّره بالعربية والإنكليزية (سؤالاً وإجابةً).',
                ], 422);
            }
            $faq->is_published = true;
            $faq->save();

            return response()->json([
                'message' => 'أصبح السؤال معروضاً في الصفحة الرئيسية.',
                'data'    => $this->present($faq),
            ]);
        }

        // نية إلغاء النشر — يجب أن يبقى عدد المنشور ≥ عدد العرض المختار
        $publishedCount = Faq::where('is_published', true)->count();
        $displayCount   = $this->displayCount();

        if ($publishedCount - 1 < $displayCount) {
            return response()->json([
                'message' => "يجب أن يبقى عدد الأسئلة المعروضة {$displayCount} على الأقل. أضِف أو انشُر سؤالاً آخر قبل إخفاء هذا.",
            ], 422);
        }

        $faq->is_published = false;
        $faq->save();

        return response()->json([
            'message' => 'تم إخفاء السؤال من الصفحة الرئيسية.',
            'data'    => $this->present($faq),
        ]);
    }

    /**
     * حذف سؤال. ممنوع إذا كان يُنقص الإجمالي تحت الحد الأدنى (4)،
     * أو يُنقص المنشور تحت عدد العرض المختار.
     */
    public function destroy($id)
    {
        $faq = Faq::findOrFail($id);

        if (Faq::count() - 1 < self::MIN_TOTAL) {
            return response()->json([
                'message' => 'لا يمكن الحذف: يجب أن يبقى عدد الأسئلة الشائعة ' . self::MIN_TOTAL . ' على الأقل.',
            ], 422);
        }

        if ($faq->is_published) {
            $publishedCount = Faq::where('is_published', true)->count();
            $displayCount   = $this->displayCount();
            if ($publishedCount - 1 < $displayCount) {
                return response()->json([
                    'message' => "لا يمكن حذف سؤال معروض إذا نقص عدد المعروض عن {$displayCount}. اعرض سؤالاً آخر أولاً.",
                ], 422);
            }
        }

        $faq->delete();

        return response()->json(['message' => 'تم حذف السؤال بنجاح.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin (👑) — الاقتراحات
    |--------------------------------------------------------------------------
    */

    public function suggestions(Request $request)
    {
        $status = $request->query('status', 'pending');

        $query = FaqSuggestion::query()->latest();
        if (in_array($status, ['pending', 'converted', 'dismissed'], true)) {
            $query->where('status', $status);
        }

        return response()->json([
            'data' => $query->get(),
            'meta' => [
                'pending_count' => FaqSuggestion::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function dismissSuggestion($id)
    {
        $suggestion = FaqSuggestion::findOrFail($id);

        if ($suggestion->status !== 'pending') {
            return response()->json(['message' => 'تمت معالجة هذا الاقتراح مسبقاً.'], 422);
        }

        $suggestion->update(['status' => 'dismissed']);

        return response()->json(['message' => 'تم تجاهل الاقتراح.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin (👑) — إعداد عدد العرض
    |--------------------------------------------------------------------------
    */

    /**
     * تغيير عدد الأسئلة المعروضة في الرئيسية (2..5).
     * لا يُسمح بقيمة تتجاوز عدد الأسئلة المنشورة الحالي.
     */
    public function setDisplayCount(Request $request)
    {
        $data = $request->validate([
            'count' => 'required|integer|min:' . self::DISPLAY_MIN . '|max:' . self::DISPLAY_MAX,
        ]);

        $publishedCount = Faq::where('is_published', true)->count();
        if ($data['count'] > $publishedCount) {
            return response()->json([
                'message' => "لا يمكن عرض {$data['count']} أسئلة بينما عدد الأسئلة المنشورة {$publishedCount} فقط. انشُر المزيد أولاً.",
            ], 422);
        }

        AppSetting::put(self::DISPLAY_COUNT_KEY, $data['count']);

        return response()->json([
            'message' => 'تم تحديث عدد الأسئلة المعروضة.',
            'data'    => ['display_count' => (int) $data['count']],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function displayCount(): int
    {
        $value = (int) AppSetting::get(self::DISPLAY_COUNT_KEY, self::DISPLAY_DEFAULT);

        return max(self::DISPLAY_MIN, min(self::DISPLAY_MAX, $value));
    }

    /**
     * تحقّق موحّد: كل الحقول الأربعة إجبارية (سؤال شائع ثنائي اللغة كامل دائماً).
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'question_en'  => 'required|string|max:500',
            'question_ar'  => 'required|string|max:500',
            'answer_en'    => 'required|string|max:5000',
            'answer_ar'    => 'required|string|max:5000',
            'is_published' => 'sometimes|boolean',
            'sort_order'   => 'sometimes|nullable|integer|min:0',
        ]);
    }

    private function present(Faq $faq): array
    {
        return [
            'id'           => $faq->id,
            'question_en'  => $faq->question_en,
            'question_ar'  => $faq->question_ar,
            'answer_en'    => $faq->answer_en,
            'answer_ar'    => $faq->answer_ar,
            'is_published' => $faq->is_published,
            'is_complete'  => $faq->isComplete(),
            'sort_order'   => $faq->sort_order,
            'created_at'   => $faq->created_at,
        ];
    }
}
