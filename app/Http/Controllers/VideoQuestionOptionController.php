<?php

namespace App\Http\Controllers;

use App\Models\VideoQuestion;
use App\Models\VideoQuestionAnswer;
use App\Models\VideoQuestionOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoQuestionOptionController extends Controller
{
    public function index(Request $request, $question_id)
    {
        $user = $request->user();

        if ($user->role === 'user') {
            $question = VideoQuestion::with('video')->findOrFail($question_id);
            if ($denied = $this->guardUserAccess($question, $user->id)) {
                return $denied;
            }
        }

        $options = VideoQuestionOption::where('question_id', $question_id)->get();

        if ($user->role === 'user') {
            $options->makeHidden('is_correct');
        }

        return response()->json([
            'status' => 'success',
            'data' => $options,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question_id' => 'required|exists:video_questions,id',
            'option_text' => 'required|string|max:500',
            'is_correct' => 'required|boolean',
        ]);

        // Enforce a single correct answer per question
        if ($validated['is_correct']) {
            VideoQuestionOption::where('question_id', $validated['question_id'])
                ->update(['is_correct' => false]);
        }

        $option = VideoQuestionOption::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Option created successfully',
            'data' => $option,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $option = VideoQuestionOption::with('question.video')->findOrFail($id);
        $user = $request->user();

        if ($user->role === 'user') {
            if ($denied = $this->guardUserAccess($option->question, $user->id)) {
                return $denied;
            }
            $option->makeHidden('is_correct');
        }

        return response()->json([
            'status' => 'success',
            'data' => $option,
        ]);
    }

    /**
     * يمنع المستخدم العادي من الوصول لخيارات سؤال يتبع فيديو مقفل، أو سؤال سبق أن أجاب عليه.
     * يُرجع استجابة خطأ عند المنع، أو null عند السماح.
     */
    private function guardUserAccess(?VideoQuestion $question, int $userId): ?JsonResponse
    {
        if (! $question || ! $question->video || ! $question->video->isUnlockedFor($userId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'هذا المحتوى غير متاح. الفيديو مقفل.',
            ], 403);
        }

        $alreadyAnswered = VideoQuestionAnswer::where('user_id', $userId)
            ->where('question_id', $question->id)
            ->exists();

        if ($alreadyAnswered) {
            return response()->json([
                'status' => 'error',
                'message' => 'لقد أجبت على هذا السؤال مسبقاً.',
            ], 403);
        }

        return null;
    }

    public function update(Request $request, $id)
    {
        $option = VideoQuestionOption::findOrFail($id);

        $validated = $request->validate([
            'option_text' => 'sometimes|string|max:500',
            'is_correct' => 'sometimes|boolean',
        ]);

        // Enforce a single correct answer per question
        if (! empty($validated['is_correct'])) {
            VideoQuestionOption::where('question_id', $option->question_id)
                ->where('id', '!=', $option->id)
                ->update(['is_correct' => false]);
        }

        $option->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Option updated successfully',
            'data' => $option,
        ]);
    }

    public function destroy($id)
    {
        $option = VideoQuestionOption::findOrFail($id);
        $option->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Option deleted successfully',
        ]);
    }
}
