<?php

namespace App\Http\Controllers;

use App\Models\VideoQuestion;
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
            ->when($user->role === 'user', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $answers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question_id' => 'required|exists:video_questions,id',
            'option_id' => 'required|exists:video_question_options,id',
        ]);

        $user = $request->user();

        $option = VideoQuestionOption::where('id', $validated['option_id'])
            ->where('question_id', $validated['question_id'])
            ->first();

        if (! $option) {
            return response()->json([
                'status' => 'error',
                'message' => 'The selected option does not belong to this question.',
            ], 422);
        }

        // فرض الحماية: لا يمكن الإجابة على سؤال يتبع فيديو مقفل
        $question = VideoQuestion::with('video')->find($validated['question_id']);
        if ($user->role === 'user' && (! $question->video || ! $question->video->isUnlockedFor($user->id))) {
            return response()->json([
                'status' => 'error',
                'message' => 'لا يمكنك الإجابة، هذا الفيديو مقفل.',
            ], 403);
        }

        // الإجابة مرة واحدة فقط: لا يمكن إعادة الإجابة أو الكتابة فوقها حتى لو كانت خاطئة
        $alreadyAnswered = VideoQuestionAnswer::where('user_id', $user->id)
            ->where('question_id', $validated['question_id'])
            ->exists();

        if ($alreadyAnswered) {
            return response()->json([
                'status' => 'error',
                'message' => 'لقد أجبت على هذا السؤال مسبقاً ولا يمكن تغيير الإجابة.',
            ], 409);
        }

        $answer = VideoQuestionAnswer::create([
            'user_id' => $user->id,
            'question_id' => $validated['question_id'],
            'option_id' => $validated['option_id'],
            'is_correct' => $option->is_correct,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Answer submitted',
            'is_correct' => $option->is_correct,
            'data' => $answer,
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
            'data' => $answer,
        ]);
    }

    public function destroy(int $id)
    {
        $answer = VideoQuestionAnswer::findOrFail($id);
        $answer->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Answer deleted successfully',
        ]);
    }
}
