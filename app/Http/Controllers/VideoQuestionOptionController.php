<?php

namespace App\Http\Controllers;

use App\Models\VideoQuestionOption;
use Illuminate\Http\Request;

class VideoQuestionOptionController extends Controller
{
    public function index(Request $request, $question_id)
    {
        $options = VideoQuestionOption::where('question_id', $question_id)->get();

        if ($request->user()->role === 'user') {
            $options->makeHidden('is_correct');
        }

        return response()->json([
            'status' => 'success',
            'data'   => $options,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question_id' => 'required|exists:video_questions,id',
            'option_text' => 'required|string|max:500',
            'is_correct'  => 'required|boolean',
        ]);

        // Enforce a single correct answer per question
        if ($validated['is_correct']) {
            VideoQuestionOption::where('question_id', $validated['question_id'])
                ->update(['is_correct' => false]);
        }

        $option = VideoQuestionOption::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Option created successfully',
            'data'    => $option,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $option = VideoQuestionOption::findOrFail($id);

        if ($request->user()->role === 'user') {
            $option->makeHidden('is_correct');
        }

        return response()->json([
            'status' => 'success',
            'data'   => $option,
        ]);
    }

    public function update(Request $request, $id)
    {
        $option = VideoQuestionOption::findOrFail($id);

        $validated = $request->validate([
            'option_text' => 'sometimes|string|max:500',
            'is_correct'  => 'sometimes|boolean',
        ]);

        // Enforce a single correct answer per question
        if (!empty($validated['is_correct'])) {
            VideoQuestionOption::where('question_id', $option->question_id)
                ->where('id', '!=', $option->id)
                ->update(['is_correct' => false]);
        }

        $option->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Option updated successfully',
            'data'    => $option,
        ]);
    }

    public function destroy($id)
    {
        $option = VideoQuestionOption::findOrFail($id);
        $option->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Option deleted successfully',
        ]);
    }
}
