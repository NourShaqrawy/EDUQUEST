<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseVideo;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class CourseVideoController extends Controller
{
    // الدقات المطلوبة
    private $resolutions = [
        '144p' => 'scale=-2:144',
        '360p' => 'scale=-2:360',
        '720p' => 'scale=-2:720',
    ];

    public function index($courseId)
    {
        $course = $this->findManagedCourse($courseId);
        $videos = $course->courseVideos()->orderBy('order')->orderBy('id')->get();

        return response()->json(['course' => $course, 'videos' => $videos]);
    }

    public function store(Request $request, $courseId)
    {
        $course = $this->findManagedCourse($courseId);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm|max:512000',
            'order' => 'sometimes|integer|min:1',
        ]);

        $paths = []; // لتخزين كافة المسارات المنتجة

        try {
            // 1. رفع الفيديو الأصلي وتوليد الدقات
            $paths = $this->processMultiResVideo($request->file('video'), $course);

            // 2. استخراج المدة
            $duration = $this->getVideoDuration(Storage::disk('public')->path($paths['original']));

            // 3. الحفظ في القاعدة (تأكد من وجود هذه الأعمدة في الموديل)
            $video = CourseVideo::create([
                'course_id' => $course->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'video_path' => $paths['original'],
                'video_144p' => $paths['144p'],
                'video_360p' => $paths['360p'],
                'video_720p' => $paths['720p'],
                'duration' => $duration,
                'order' => $validated['order'] ?? (($course->courseVideos()->max('order') ?? 0) + 1),
            ]);

            return response()->json(['message' => 'تم رفع الدرس بكافة الجودات', 'video' => $video], 201);
        } catch (Throwable $e) {
            $this->deleteStoredFiles($paths);
            report($e);
            return response()->json(['message' => 'فشل في معالجة الفيديو: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $courseId, $videoId)
    {
        $course = $this->findManagedCourse($courseId);
        $video = $course->courseVideos()->findOrFail($videoId);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'video' => 'sometimes|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm|max:512000',
            'order' => 'sometimes|integer|min:1',
        ]);

        $newPaths = [];
        // الاحتفاظ بالمسارات القديمة لحذفها لاحقاً
        $oldPaths = [
            $video->video_path,
            $video->video_144p,
            $video->video_360p,
            $video->video_720p
        ];

        try {
            $updateData = $request->only(['title', 'description', 'order']);

            if ($request->hasFile('video')) {
                // معالجة الفيديو الجديد
                $newPaths = $this->processMultiResVideo($request->file('video'), $course);
                
                $updateData['video_path'] = $newPaths['original'];
                $updateData['video_144p'] = $newPaths['144p'];
                $updateData['video_360p'] = $newPaths['360p'];
                $updateData['video_720p'] = $newPaths['720p'];
                $updateData['duration'] = $this->getVideoDuration(Storage::disk('public')->path($newPaths['original']));

                // حذف الملفات القديمة من السيرفر فور نجاح المعالجة الجديدة
                $this->deleteStoredFiles($oldPaths);
            }

            $video->update($updateData);

            return response()->json(['message' => 'تم التحديث وحذف الملفات القديمة', 'video' => $video->fresh()]);
        } catch (Throwable $e) {
            if (!empty($newPaths)) $this->deleteStoredFiles($newPaths);
            report($e);
            return response()->json(['message' => 'فشل التحديث'], 500);
        }
    }

    public function destroy($courseId, $videoId)
    {
        $course = $this->findManagedCourse($courseId);
        $video = $course->courseVideos()->findOrFail($videoId);

        $paths = [$video->video_path, $video->video_144p, $video->video_360p, $video->video_720p];
        
        $video->delete();
        $this->deleteStoredFiles($paths);

        return response()->json(['message' => 'تم حذف الدرس وملفاته نهائياً']);
    }

    // --- وظائف المساعدة المعالجة ---

    private function processMultiResVideo(UploadedFile $videoFile, Course $course): array
    {
        $baseName = uniqid('lesson_', true);
        $extension = $videoFile->getClientOriginalExtension();
        $directory = "course-videos/{$course->id}/$baseName";
        
        Storage::disk('public')->makeDirectory($directory);

        // حفظ الأصلي
        $originalPath = $videoFile->storeAs($directory, "original.$extension", 'public');
        $fullOriginalPath = Storage::disk('public')->path($originalPath);

        $results = ['original' => $originalPath];

        // توليد كل دقة
        foreach ($this->resolutions as $label => $scale) {
            $fileName = "video_{$label}.mp4";
            $targetPath = Storage::disk('public')->path("$directory/$fileName");
            
            $this->runFfmpegCompression($fullOriginalPath, $targetPath, $scale);
            $results[$label] = "$directory/$fileName";
        }

        return $results;
    }

    private function runFfmpegCompression($source, $target, $scale)
    {
        $process = new Process([
            'ffmpeg', '-y', '-i', $source,
            '-vf', $scale,
            '-vcodec', 'libx264',
            '-crf', '28', 
            '-preset', 'veryfast',
            '-acodec', 'aac',
            $target
        ]);

        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("خطأ في إنتاج جودة {$scale}: " . $process->getErrorOutput());
        }
    }

    private function getVideoDuration(string $fullPath): int
    {
        $process = new Process([
            'ffprobe', '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $fullPath
        ]);
        $process->run();
        return $process->isSuccessful() ? (int) round((float) trim($process->getOutput())) : 0;
    }

    private function deleteStoredFiles(array $paths): void
    {
        $filtered = array_filter($paths);
        if (!empty($filtered)) {
            Storage::disk('public')->delete($filtered);
        }
    }

    private function findManagedCourse($courseId): Course
    {
        $course = Course::findOrFail($courseId);
        if (Auth::user()->role !== 'admin' && $course->publisher_id !== Auth::id()) {
            abort(403, 'غير مسموح لك بالوصول');
        }
        return $course;
    }
}