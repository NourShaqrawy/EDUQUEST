<?php

namespace App\Http\Controllers;

use App\Models\VideoQuestion;
use Illuminate\Http\Request;

class VideoQuestionController extends Controller
{
    public function index(Request $request, $video_id)
    {
        $questions = VideoQuestion::with('options')
            ->where('video_id', $video_id)
            ->orderBy('time_in_video')
            ->get();

        if ($request->user()->role === 'user') {
            $questions->each(fn($q) => $q->options->makeHidden('is_correct'));
        }

        return response()->json([
            'status' => 'success',
            'data'   => $questions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'video_id'      => 'required|exists:course_videos,id',
            'question'      => 'required|string|max:1000',
            'time_in_video' => 'required|integer|min:0',
        ]);

        $question = VideoQuestion::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Question created successfully',
            'data'    => $question,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $question = VideoQuestion::with('options')->findOrFail($id);

        if ($request->user()->role === 'user') {
            $question->options->makeHidden('is_correct');
        }

        return response()->json([
            'status' => 'success',
            'data'   => $question,
        ]);
    }

    public function update(Request $request, $id)
    {
        $question = VideoQuestion::findOrFail($id);

        $validated = $request->validate([
            'question'      => 'sometimes|string|max:1000',
            'time_in_video' => 'sometimes|integer|min:0',
        ]);

        $question->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Question updated successfully',
            'data'    => $question,
        ]);
    }

    public function destroy($id)
    {
        $question = VideoQuestion::findOrFail($id);
        $question->delete(); // DB cascadeOnDelete handles options and answers

        return response()->json([
            'status'  => 'success',
            'message' => 'Question deleted successfully',
        ]);
    }
}
