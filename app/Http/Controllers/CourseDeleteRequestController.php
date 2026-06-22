<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseDeleteRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseDeleteRequestController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * الناشر يتقدم بطلب حذف لكورسه الخاص.
     */
    public function store(Request $request, $courseId)
    {
        $publisher = Auth::user();
        $course    = Course::findOrFail($courseId);

        // يجب أن يكون الناشر مالك الكورس
        if ($course->publisher_id !== $publisher->id) {
            return response()->json(['message' => 'You do not own this course.'], 403);
        }

        // لا يوجد طلب معلق مسبق
        $existing = CourseDeleteRequest::where('course_id', $courseId)
            ->where('publisher_id', $publisher->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'لديك طلب حذف معلق لهذا الكورس بالفعل.',
            ], 422);
        }

        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $deleteRequest = CourseDeleteRequest::create([
            'course_id'    => $courseId,
            'publisher_id' => $publisher->id,
            'reason'       => $request->reason ?? null,
            'status'       => 'pending',
        ]);

        // إشعار جميع الأدمنز
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $this->notifications->send(
                $admin->id,
                'طلب حذف كورس',
                "طلب الناشر \"{$publisher->name}\" حذف كورسه \"{$course->title}\".",
                'course_delete_request',
                [
                    'course_id'         => $course->id,
                    'delete_request_id' => $deleteRequest->id,
                    'title_en'          => 'Course deletion request',
                    'title_ar'          => 'طلب حذف كورس',
                    'body_en'           => "Publisher \"{$publisher->name}\" requested deletion of their course \"{$course->title}\".",
                    'body_ar'           => "طلب الناشر \"{$publisher->name}\" حذف كورسه \"{$course->title}\".",
                ],
            );
        }

        return response()->json([
            'message'        => 'تم إرسال طلب الحذف بنجاح، سيتم مراجعته من قِبَل الإدارة.',
            'delete_request' => $deleteRequest->load('course', 'publisher'),
        ], 201);
    }

    /**
     * Admin يعرض جميع طلبات الحذف (مع الفلترة بالحالة).
     */
    public function index(Request $request)
    {
        $status   = $request->query('status'); // pending | approved | rejected
        $query    = CourseDeleteRequest::with(['course', 'publisher'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return response()->json($query->get());
    }

    /**
     * Admin يوافق على طلب الحذف — يُحذف الكورس ويُشعَر الناشر.
     */
    public function approve(Request $request, $id)
    {
        $deleteRequest = CourseDeleteRequest::with(['course.publisher', 'publisher'])->findOrFail($id);

        if ($deleteRequest->status !== 'pending') {
            return response()->json(['message' => 'هذا الطلب تمت معالجته مسبقاً.'], 422);
        }

        $course    = $deleteRequest->course;
        $publisher = $deleteRequest->publisher;

        // تحديث حالة الطلب قبل حذف الكورس (لأن cascade قد يحذف الطلب)
        $deleteRequest->update(['status' => 'approved']);

        if ($course) {
            $courseTitle = $course->title;
            $course->delete();
        } else {
            $courseTitle = '(محذوف)';
        }

        // إشعار الناشر
        if ($publisher) {
            $this->notifications->send(
                $publisher->id,
                'تمت الموافقة على طلب الحذف',
                "تمت الموافقة على طلب حذف كورسك \"{$courseTitle}\" وتم حذفه.",
                'course_delete_approved',
                [
                    'delete_request_id' => $deleteRequest->id,
                    'title_en'          => 'Course deletion approved',
                    'title_ar'          => 'تمت الموافقة على طلب الحذف',
                    'body_en'           => "Your deletion request for course \"{$courseTitle}\" has been approved and the course has been deleted.",
                    'body_ar'           => "تمت الموافقة على طلب حذف كورسك \"{$courseTitle}\" وتم حذفه.",
                ],
            );
        }

        return response()->json([
            'message' => 'تمت الموافقة على طلب الحذف وتم حذف الكورس.',
        ]);
    }

    /**
     * Admin يرفض طلب الحذف — يُشعَر الناشر مع السبب.
     */
    public function reject(Request $request, $id)
    {
        $deleteRequest = CourseDeleteRequest::with(['course', 'publisher'])->findOrFail($id);

        if ($deleteRequest->status !== 'pending') {
            return response()->json(['message' => 'هذا الطلب تمت معالجته مسبقاً.'], 422);
        }

        $request->validate([
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $adminNote = $request->input('admin_note', '');
        $deleteRequest->update([
            'status'     => 'rejected',
            'admin_note' => $adminNote ?: null,
        ]);

        $courseTitle = $deleteRequest->course?->title ?? '(غير معروف)';
        $publisher   = $deleteRequest->publisher;

        // إشعار الناشر
        if ($publisher) {
            $bodyAr = "تم رفض طلب حذف كورسك \"{$courseTitle}\".";
            $bodyEn = "Your deletion request for course \"{$courseTitle}\" has been rejected.";
            if ($adminNote) {
                $bodyAr .= " السبب: {$adminNote}";
                $bodyEn .= " Reason: {$adminNote}";
            }

            $this->notifications->send(
                $publisher->id,
                'تم رفض طلب الحذف',
                $bodyAr,
                'course_delete_rejected',
                [
                    'delete_request_id' => $deleteRequest->id,
                    'title_en'          => 'Course deletion rejected',
                    'title_ar'          => 'تم رفض طلب الحذف',
                    'body_en'           => $bodyEn,
                    'body_ar'           => $bodyAr,
                ],
            );
        }

        return response()->json([
            'message'        => 'تم رفض طلب الحذف.',
            'delete_request' => $deleteRequest,
        ]);
    }
}
