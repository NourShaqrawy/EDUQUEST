<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesCourses;
use App\Models\CourseVideo;
use App\Models\VideoQuestion;
use App\Models\VideoQuestionAnswer;
use Illuminate\Http\Request;

class VideoQuestionController extends Controller
{
    use ManagesCourses;

    public function index(Request $request, $video_id)
    {
        $video = CourseVideo::findOrFail($video_id);
        $user = $request->user();

        $query = VideoQuestion::with('options')
            ->where('video_id', $video_id)
            ->orderBy('time_in_video');

        if ($user->role === 'user') {
            // الفيديو يجب أن يكون مفتوحاً للطالب
            if (! $video->isUnlockedFor($user->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'هذا الفيديو مقفل. عليك إكمال الفيديو السابق أولاً.',
                ], 403);
            }

            // إخفاء الأسئلة التي سبق أن أجاب عليها المستخدم (كل تمرين يظهر مرة واحدة فقط)
            $answeredQuestionIds = VideoQuestionAnswer::where('user_id', $user->id)
                ->whereIn('question_id', $video->videoQuestions()->select('id'))
                ->pluck('question_id');

            $query->whereNotIn('id', $answeredQuestionIds);
        }

        $questions = $query->get();

        if ($user->role === 'user') {
            $questions->each(fn ($q) => $q->options->makeHidden('is_correct'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $questions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'video_id' => 'required|exists:course_videos,id',
            'question' => 'required|string|max:1000',
            'time_in_video' => 'required|integer|min:0',
        ]);

        $video = CourseVideo::with('course')->findOrFail($validated['video_id']);
        $this->assertManagesCourse($video->course);
        $this->assertCourseEditable($video->course);

        $question = VideoQuestion::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Question created successfully',
            'data' => $question,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $question = VideoQuestion::with(['options', 'video'])->findOrFail($id);
        $user = $request->user();

        if ($user->role === 'user') {
            // الفيديو يجب أن يكون مفتوحاً
            if (! $question->video || ! $question->video->isUnlockedFor($user->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'هذا السؤال غير متاح. الفيديو مقفل.',
                ], 403);
            }

            // السؤال المُجاب عليه لا يظهر مرة أخرى للمستخدم
            $alreadyAnswered = VideoQuestionAnswer::where('user_id', $user->id)
                ->where('question_id', $question->id)
                ->exists();

            if ($alreadyAnswered) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'لقد أجبت على هذا السؤال مسبقاً.',
                ], 403);
            }

            $question->options->makeHidden('is_correct');
        }

        return response()->json([
            'status' => 'success',
            'data' => $question,
        ]);
    }

    public function update(Request $request, $id)
    {
        $question = VideoQuestion::with('video.course')->findOrFail($id);
        $this->assertManagesCourse($question->video->course);
        $this->assertCourseEditable($question->video->course);

        $validated = $request->validate([
            'question' => 'sometimes|string|max:1000',
            'time_in_video' => 'sometimes|integer|min:0',
        ]);

        $question->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Question updated successfully',
            'data' => $question,
        ]);
    }

    public function destroy($id)
    {
        $question = VideoQuestion::with('video.course')->findOrFail($id);
        $this->assertManagesCourse($question->video->course);
        $this->assertCourseEditable($question->video->course);

        $question->delete(); // DB cascadeOnDelete handles options and answers

        return response()->json([
            'status' => 'success',
            'message' => 'Question deleted successfully',
        ]);
    }
}
