<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * بثّ ملفات الوسائط من قرص public مع دعم ترويسة Range (206 Partial Content).
 *
 * السبب: خادم التطوير المدمج في PHP (`artisan serve`) يتجاهل ترويسة Range
 * ويعيد الملف كاملاً برمز 200. بدون 206 لا يستطيع المتصفح الانتقال إلى لحظة
 * زمنية، فينهار كلٌّ من السحب على شريط التقدّم واستعادة الموضع عند تبديل الجودة.
 *
 * response()->file() يُنتج BinaryFileResponse الذي يعالج Range/If-Range ويعيد
 * 206 + Content-Range + Accept-Ranges (و416 عند نطاق غير صالح) تلقائياً.
 *
 * الحماية: مطابقة تماماً للسلوك السابق (روابط /storage الثابتة كانت غير محمية
 * أيضاً). الحماية الفعلية تتم بإخفاء حقول url_* للدروس المقفلة في
 * LessonResource و CourseVideoController. هذا المسار لا يضيف حمايةً ولا ينقصها.
 */
class MediaController extends Controller
{
    public function show(string $path): BinaryFileResponse
    {
        // منع الخروج من مجلد التخزين (path traversal) وحصر البثّ بمجلد الفيديوهات.
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (str_contains($path, '..') || ! preg_match('#^course-videos/[A-Za-z0-9._/-]+\.mp4$#', $path)) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $response = response()->file($disk->path($path), [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'public, max-age=3600',
        ]);

        // مهم لتبديل الجودة: المتصفح يرسل If-Range مع مُحقِّق (ETag/Last-Modified).
        // بدون مُحقِّق صالح يتجاهل Symfony النطاق ويعيد الملف كاملاً برمز 200،
        // فتعود المشكلة من جديد.
        return $response->setAutoLastModified()->setAutoEtag();
    }
}
