<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VideoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $videos = Video::where('course_id', $validated['course_id'])
            ->select('id', 'course_id', 'title', 'description', 'video_url', 'video_order', 'duration')
            ->orderBy('video_order', 'asc')
            ->get();

        return response()->json([
            'message' => 'Videos retrieved successfully',
            'videos' => $videos,
        ], 200);
    }
    public function store(Request $request): JsonResponse
    {
        
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'required|url',
            'video_order' => 'nullable|integer|min:1',
            'duration' => 'required|date_format:H:i:s',
        ]);

        $video = Video::create($validated);

        return response()->json([
            'message' => 'Video uploaded successfully',
            'video' => $video,
        ], 201);
    }
}