<?php

namespace App\Http\Controllers;

use App\Models\VideoQuestionAnswer;
use App\Models\VideoQuestionOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VideoQuestionAnswerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $answers = VideoQuestionAnswer::with(['question', 'option'])
            ->when($user->role === 'user', fn($q) => $q->where('user_id', $user->id))
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $answers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question_id' => 'required|exists:video_questions,id',
            'option_id'   => 'required|exists:video_question_options,id',
        ]);

        $option = VideoQuestionOption::where('id', $validated['option_id'])
            ->where('question_id', $validated['question_id'])
            ->first();

        if (!$option) {
            return response()->json([
                'status'  => 'error',
                'message' => 'The selected option does not belong to this question.',
            ], 422);
        }

        $answer = VideoQuestionAnswer::updateOrCreate(
            ['user_id' => Auth::id(), 'question_id' => $validated['question_id']],
            ['option_id' => $validated['option_id'], 'is_correct' => $option->is_correct],
        );

        return response()->json([
            'status'     => 'success',
            'message'    => 'Answer submitted',
            'is_correct' => $option->is_correct,
            'data'       => $answer,
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        $answer = VideoQuestionAnswer::with(['question', 'option'])->findOrFail($id);

        if ($request->user()->role === 'user' && $answer->user_id !== Auth::id()) {
            abort(403, 'You do not have permission to view this answer.');
        }

        return response()->json([
            'status' => 'success',
            'data'   => $answer,
        ]);
    }

    public function destroy(int $id)
    {
        $answer = VideoQuestionAnswer::findOrFail($id);
        $answer->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Answer deleted successfully',
        ]);
    }
}
