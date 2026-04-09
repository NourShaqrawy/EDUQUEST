<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * عرض جميع الكورسات
     */
    public function index()
    {
        // $courses = Course::with(['category', 'publisher'])->get();
        $courses = Course::get();
        return response()->json($courses);
    }

    /**
     * إنشاء كورس جديد مع رفع الصورة والربط بالفئة والناشر
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'category_id' => 'required|exists:categories,id',
            'publisher_id' => 'required|exists:users,id',
        ]);

        if ($request->hasFile('thumbnail')) {
            $path = $request->file('thumbnail')->store('courses/thumbnails', 'public');
            $validatedData['thumbnail'] = $path;
        }

        $course = Course::create($validatedData);

        return response()->json(['message' => 'تم إنشاء الكورس بنجاح', 'course' => $course], 201);
    }

    /**
     * عرض كورس معين بالتفصيل
     */
    public function show($id)
    {
        // $course = Course::with(['category', 'publisher'])->findOrFail($id);
        $course = Course::findOrFail($id);
        return response()->json([$course]);
    }

    /**
     * عرض كورسات الناشر الحالي فقط
     */
    public function myCourses()
    {
        // جلب المستخدم الحالي وكورساته
        $courses = Course::where('publisher_id', Auth::id())
            ->get();

        return response()->json($courses);
    }

    /**
     * تعديل بيانات الكورس (للناشر فقط)
     */
    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        // التحقق من أن المستخدم الحالي هو صاحب الكورس
        if ($course->publisher_id !== Auth::id()) {
            return response()->json(['message' => 'غير مسموح لك بتعديل هذا الكورس'], 403);
        }

        $validatedData = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'thumbnail' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
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
     * حذف الكورس (للناشر فقط)
     */
    public function destroy($id)
    {
        $course = Course::findOrFail($id);

        // التحقق من أن المستخدم الحالي هو صاحب الكورس
        if ($course->publisher_id !== Auth::id() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'غير مسموح لك بحذف هذا الكورس'], 403);
        }

        Storage::disk('public')->delete($course->thumbnail);
        $course->delete();

        return response()->json(['message' => 'تم حذف الكورس بنجاح']);
    }
}
