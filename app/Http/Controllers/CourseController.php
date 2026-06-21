<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * عرض الكورسات المعتمدة فقط للزوار والطلاب.
     */
    public function index()
    {
        $courses = Course::where('status', 'approved')->get();
        return response()->json($courses);
    }

    /**
     * إنشاء كورس جديد — يُحفظ بحالة "pending" وتُرسَل إشعارات للمسؤولين.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'required|string',
            'thumbnail'    => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'category_id'  => 'required|exists:categories,id',
            'publisher_id' => 'required|exists:users,id',
        ]);

        if ($request->hasFile('thumbnail')) {
            $path = $request->file('thumbnail')->store('courses/thumbnails', 'public');
            $validatedData['thumbnail'] = $path;
        }

        $validatedData['status'] = 'pending';
        $course = Course::create($validatedData);

        // أرسل إشعاراً لكل المسؤولين (admin)
        $publisher = Auth::user();
        $admins    = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $this->notifications->send(
                $admin->id,
                'كورس جديد بانتظار المراجعة',
                "قام الناشر \"{$publisher->name}\" برفع كورس جديد بعنوان \"{$course->title}\" — يرجى مراجعته.",
                'course_pending',
                [
                    'course_id' => $course->id,
                    'title_en'  => 'New course pending review',
                    'title_ar'  => 'كورس جديد بانتظار المراجعة',
                    'body_en'   => "Publisher \"{$publisher->name}\" uploaded a new course \"{$course->title}\" — please review it.",
                    'body_ar'   => "قام الناشر \"{$publisher->name}\" برفع كورس جديد بعنوان \"{$course->title}\" — يرجى مراجعته.",
                ],
            );
        }

        return response()->json(['message' => 'تم إنشاء الكورس بنجاح وهو بانتظار موافقة الإدارة', 'course' => $course], 201);
    }

    /**
     * عرض كورس واحد — متاح للجميع (الكورس المعتمد) أو للمالك/الأدمن.
     */
    public function show($id)
    {
        $course = Course::findOrFail($id);
        return response()->json([$course]);
    }

    /**
     * كورسات الناشر الحالي فقط (جميع الحالات).
     */
    public function myCourses()
    {
        $user = Auth::user();

        // الأدمن يرى جميع الكورسات، الناشر يرى كورساته فقط
        if ($user->role === 'admin') {
            $courses = Course::get();
        } else {
            $courses = Course::where('publisher_id', $user->id)->get();
        }

        return response()->json($courses);
    }

    /**
     * الكورسات المعلقة بانتظار الموافقة (أدمن فقط).
     */
    public function pending()
    {
        $courses = Course::with(['publisher', 'category'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json($courses);
    }

    /**
     * الموافقة على كورس (أدمن فقط) وإشعار الناشر.
     */
    public function approve($id)
    {
        $course = Course::with('publisher')->findOrFail($id);

        if ($course->status === 'approved') {
            return response()->json(['message' => 'الكورس معتمد مسبقاً'], 422);
        }

        $course->update(['status' => 'approved']);

        if ($course->publisher) {
            $this->notifications->send(
                $course->publisher->id,
                'تمت الموافقة على كورسك',
                "تمت الموافقة على كورسك \"{$course->title}\" وأصبح متاحاً للطلاب.",
                'course_approved',
                [
                    'course_id' => $course->id,
                    'title_en'  => 'Your course has been approved',
                    'title_ar'  => 'تمت الموافقة على كورسك',
                    'body_en'   => "Your course \"{$course->title}\" has been approved and is now available to students.",
                    'body_ar'   => "تمت الموافقة على كورسك \"{$course->title}\" وأصبح متاحاً للطلاب.",
                ],
            );
        }

        return response()->json(['message' => 'تمت الموافقة على الكورس', 'course' => $course]);
    }

    /**
     * رفض كورس (أدمن فقط) وإشعار الناشر.
     */
    public function reject(Request $request, $id)
    {
        $course = Course::with('publisher')->findOrFail($id);

        if ($course->status === 'rejected') {
            return response()->json(['message' => 'الكورس مرفوض مسبقاً'], 422);
        }

        $reason = $request->input('reason', '');
        $course->update(['status' => 'rejected']);

        if ($course->publisher) {
            $bodyAr = "للأسف تم رفض كورسك \"{$course->title}\".";
            $bodyEn = "Unfortunately, your course \"{$course->title}\" has been rejected.";
            if ($reason) {
                $bodyAr .= " السبب: {$reason}";
                $bodyEn .= " Reason: {$reason}";
            }
            $this->notifications->send(
                $course->publisher->id,
                'تم رفض كورسك',
                $bodyAr,
                'course_rejected',
                [
                    'course_id' => $course->id,
                    'title_en'  => 'Your course has been rejected',
                    'title_ar'  => 'تم رفض كورسك',
                    'body_en'   => $bodyEn,
                    'body_ar'   => $bodyAr,
                ],
            );
        }

        return response()->json(['message' => 'تم رفض الكورس', 'course' => $course]);
    }

    /**
     * تعديل بيانات الكورس (للناشر أو الأدمن).
     */
    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        if (Auth::user()->role !== 'admin' && $course->publisher_id !== Auth::id()) {
            return response()->json(['message' => 'غير مسموح لك بتعديل هذا الكورس'], 403);
        }

        $validatedData = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'thumbnail'   => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'category_id' => 'sometimes|exists:categories,id',
        ]);

        if ($request->hasFile('thumbnail')) {
            Storage::disk('public')->delete($course->thumbnail);
            $path = $request->file('thumbnail')->store('courses/thumbnails', 'public');
            $validatedData['thumbnail'] = $path;
        }

        $course->update($validatedData);

        return response()->json(['message' => 'تم التحديث بنجاح', 'course' => $course]);
    }

    /**
     * حذف الكورس.
     */
    public function destroy($id)
    {
        $course = Course::findOrFail($id);

        if (Auth::user()->role !== 'admin' && $course->publisher_id !== Auth::id()) {
            return response()->json(['message' => 'غير مسموح لك بحذف هذا الكورس'], 403);
        }

        Storage::disk('public')->delete($course->thumbnail);
        $course->delete();

        return response()->json(['message' => 'تم حذف الكورس بنجاح']);
    }
}
