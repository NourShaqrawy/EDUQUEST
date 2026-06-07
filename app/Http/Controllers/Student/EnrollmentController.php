<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Models\Course;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    /** كورسات الطالب المسجَّلة. */
    public function index(Request $request)
    {
        $enrollments = $request->user()
            ->enrollments()
            ->with('course')
            ->latest('enrolled_at')
            ->get();

        return $this->success(EnrollmentResource::collection($enrollments));
    }

    /** التسجيل في كورس. */
    public function store(Request $request, Course $course)
    {
        $enrollment = $this->enrollments->enroll($request->user(), $course);

        return $this->success(
            EnrollmentResource::make($enrollment),
            'تم التسجيل في الكورس بنجاح.',
            201
        );
    }
}
