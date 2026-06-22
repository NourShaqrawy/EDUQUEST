<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesCourses;
use App\Models\Course;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    use ManagesCourses;

    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * عرض الكورسات المعتمدة والمكتملة فقط للزوار والطلاب.
     */
    public function index()
    {
        $courses = Course::where('status', 'approved')
            ->where('completion_status', 'completed')
            ->get();

        return response()->json($courses);
    }

    /**
     * إنشاء كورس جديد — يُحفظ بحالة "pending" وتُرسَل إشعارات للمسؤولين.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'required|string',
            'thumbnail'       => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'category_id'     => 'required|exists:categories,id',
            'publisher_id'    => 'required|exists:users,id',
            'has_certificate' => 'required|boolean',
        ]);

        if ($request->hasFile('thumbnail')) {
            $path = $request->file('thumbnail')->store('courses/thumbnails', 'public');
            $validatedData['thumbnail'] = $path;
        }

        // كل كورس جديد يبدأ بانتظار المراجعة وفي وضع التطوير (غير مكتمل).
        $validatedData['status'] = 'pending';
        $validatedData['completion_status'] = 'ongoing';
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
     * الكورسات المرفوضة (أدمن فقط).
     */
    public function rejected()
    {
        $courses = Course::with(['publisher', 'category'])
            ->where('status', 'rejected')
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
        $course->update(['status' => 'rejected', 'rejection_reason' => $reason ?: null]);

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
            'title'           => 'sometimes|string|max:255',
            'description'     => 'sometimes|string',
            'thumbnail'       => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'category_id'     => 'sometimes|exists:categories,id',
            'has_certificate' => 'sometimes|boolean',
        ]);

        // تغيير نوع الكورس (ذو شهادة ↔ تعريفي) مسموح فقط طالما الكورس قيد التطوير (ongoing).
        if (array_key_exists('has_certificate', $validatedData)) {
            $newHasCertificate = (bool) $validatedData['has_certificate'];
            $validatedData['has_certificate'] = $newHasCertificate;

            if ($newHasCertificate !== (bool) $course->has_certificate) {
                if ($course->completion_status === 'completed') {
                    return response()->json([
                        'message' => 'لا يمكن تغيير نوع الكورس بعد اكتماله. أرجِع الكورس إلى وضع التعديل أولاً.',
                    ], 422);
                }

                // التحويل إلى كورس تعريفي يحذف الامتحان وأسئلته (cascade على مستوى قاعدة البيانات).
                if ($newHasCertificate === false && $course->exam) {
                    $course->exam->delete();
                }
            }
        }

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

    /**
     * قائمة تحقّق جاهزية الكورس ليُصبح مكتملاً (للناشر/الأدمن).
     */
    public function completionReadiness($id)
    {
        $course = Course::findOrFail($id);
        $this->assertManagesCourse($course);

        return response()->json([
            'status' => 'success',
            'data'   => $this->completionReadinessData($course),
        ]);
    }

    /**
     * جعل الكورس مكتملاً — بعد استيفاء كل المتطلبات. الطالب لا يرى إلا الكورس المكتمل والمعتمد.
     */
    public function markComplete($id)
    {
        $course = Course::findOrFail($id);
        $this->assertManagesCourse($course);

        if ($course->completion_status === 'completed') {
            return response()->json(['message' => 'الكورس مكتمل بالفعل.', 'course' => $course]);
        }

        $readiness = $this->completionReadinessData($course);

        if (! $readiness['ready']) {
            return response()->json([
                'status'  => 'error',
                'message' => $this->completionBlockerMessage($readiness['checks'], $course),
                'checks'  => $readiness['checks'],
            ], 422);
        }

        $course->update(['completion_status' => 'completed']);

        return response()->json(['message' => 'تم وضع الكورس كمكتمل.', 'course' => $course]);
    }

    /**
     * إرجاع الكورس إلى وضع التعديل (مستمر). يختفي مؤقتاً عن الطلاب ويُفتح المحتوى للتعديل.
     * لا يُلغى نشر الامتحان ولا تُلغى تسجيلات الطلاب الحاليين.
     */
    public function reopen($id)
    {
        $course = Course::findOrFail($id);
        $this->assertManagesCourse($course);

        if ($course->completion_status === 'ongoing') {
            return response()->json(['message' => 'الكورس بالفعل في وضع التعديل.', 'course' => $course]);
        }

        $course->update(['completion_status' => 'ongoing']);

        return response()->json(['message' => 'تم إرجاع الكورس إلى وضع التعديل.', 'course' => $course]);
    }

    /**
     * يحسب جاهزية الكورس للاكتمال: درس واحد على الأقل، وللكورس ذي الشهادة امتحان منشور
     * وكل درس فيه سؤال واحد على الأقل.
     */
    private function completionReadinessData(Course $course): array
    {
        $hasVideos = $course->courseVideos()->exists();

        $checks = ['has_videos' => $hasVideos];

        if ($course->has_certificate) {
            $exam = $course->exam;
            $checks['exam_published'] = (bool) ($exam && $exam->is_published);

            $checks['all_videos_have_questions'] = $hasVideos
                && ! $course->courseVideos()->whereDoesntHave('videoQuestions')->exists();
        }

        return [
            'has_certificate'   => (bool) $course->has_certificate,
            'completion_status' => $course->completion_status,
            'ready'             => ! in_array(false, $checks, true),
            'checks'            => $checks,
        ];
    }

    /** رسالة عربية دقيقة تحدّد أول متطلّب ناقص لإكمال الكورس. */
    private function completionBlockerMessage(array $checks, Course $course): string
    {
        if (empty($checks['has_videos'])) {
            return 'لا يمكن إكمال الكورس: يجب إضافة درس واحد على الأقل.';
        }

        if ($course->has_certificate) {
            if (empty($checks['all_videos_have_questions'])) {
                return 'لا يمكن إكمال الكورس: يجب أن يحتوي كل درس على سؤال واحد على الأقل.';
            }
            if (empty($checks['exam_published'])) {
                return 'لا يمكن إكمال الكورس: يجب نشر امتحان مستوفٍ للقيود (من 10 إلى 35 سؤالاً).';
            }
        }

        return 'لا يمكن إكمال الكورس: بعض المتطلبات غير مكتملة.';
    }
}
