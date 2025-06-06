<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Exercise_Submissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    public function issueCertificate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
        ]);

        $studentId = $validated['student_id'];
        $courseId = $validated['course_id'];

        // Check if the student is enrolled in the course
        $enrollment = Course::find($courseId)->enrollments()->where('student_id', $studentId)->first();
        if (!$enrollment) {
            return response()->json(['message' => 'Student is not enrolled in this course'], 403);
        }

        // Retrieve all exercise submissions for the course
        $submissions = Exercise_Submissions::where('student_id', $studentId)
            ->whereIn('exercise_id', function ($query) use ($courseId) {
                $query->select('id')
                    ->from('exercises')
                    ->whereIn('video_id', function ($subQuery) use ($courseId) {
                        $subQuery->select('id')
                            ->from('videos')
                            ->where('course_id', $courseId);
                    });
            })
            ->get();

        // Check if there are any submissions
        if ($submissions->isEmpty()) {
            return response()->json(['message' => 'No submissions found for this course'], 400);
        }

        // Calculate the average score
        $averageScore = $submissions->avg('score');
        $passingScore = 70; // Minimum passing score (configurable)

        if ($averageScore < $passingScore) {
            return response()->json([
                'message' => 'Student did not meet the passing score',
                'average_score' => $averageScore,
            ], 400);
        }

        // Check if a certificate already exists
        $existingCertificate = Certificate::where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->first();

        if ($existingCertificate) {
            return response()->json([
                'message' => 'Certificate already issued',
                'certificate' => $existingCertificate,
            ], 200);
        }

        // Issue a new certificate
        $certificate = Certificate::create([
            'student_id' => $studentId,
            'course_id' => $courseId,
            'certificate_code' => Str::random(16), // Generate a unique certificate code
        ]);

        return response()->json([
            'message' => 'Certificate issued successfully',
            'certificate' => $certificate,
        ], 201);
    }
}