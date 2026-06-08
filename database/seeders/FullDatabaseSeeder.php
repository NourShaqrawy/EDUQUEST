<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * سيدر شامل يملأ كل جداول القاعدة ببيانات ضخمة ومتنوّعة:
 * users, categories, courses, course_videos, video_questions, video_question_options,
 * video_question_answers, course_certificates, enrollments, lesson_progress,
 * course_exams, exam_questions, exam_question_options, exam_attempts,
 * exam_attempt_answers, course_results, notifications.
 *
 * الصور والفيديوهات: يُنسخ فيديو حقيقي وصورة حقيقية مرّة واحدة فقط إلى disk('public')
 * وفق مسارات التخزين المعتمدة في المشروع، وتشير جميع الكورسات/الدروس إلى نفس الملف
 * (خفيف على القرص). الروابط تُولَّد عبر asset() اعتماداً على APP_URL، لذا تأكّد أنّ
 * APP_URL يطابق رابط الخادم (مثل http://127.0.0.1:8000) كي تُعرض في الفرونت دون أخطاء.
 *
 * التشغيل:  php artisan db:seed --class=FullDatabaseSeeder
 *      أو:  php artisan migrate:fresh --seed --seeder=FullDatabaseSeeder
 */
class FullDatabaseSeeder extends Seeder
{
    /** مصدر الفيديو الحقيقي على جهاز المستخدم. */
    private const SOURCE_VIDEO = 'C:\\Users\\mohammad_bohlak\\Videos\\تسجيلات الشاشة\\جارٍ تسجيل الشاشة 2026-06-07 191943.mp4';

    /** مصدر الصورة الحقيقية على جهاز المستخدم. */
    private const SOURCE_IMAGE = 'C:\\Users\\mohammad_bohlak\\Pictures\\Screenshots\\لقطة شاشة 2026-04-23 094104.png';

    /** المسار النسبي المشترك للصورة بعد نسخها. */
    private string $sharedThumbnail;

    /** مسارات الفيديو المشتركة (نفس الملف لكل الجودات). */
    private array $sharedVideo;

    public function run(): void
    {
        $disk = Storage::disk('public');

        // ننسخ الصورة والفيديو مرّة واحدة فقط (خفيف على القرص)
        $this->sharedThumbnail = $this->copySharedThumbnail($disk);
        $this->sharedVideo     = $this->copySharedVideo($disk);

        // نُعطّل فحص المفاتيح الأجنبية مؤقتاً لتسريع الإدراج الجماعي (MySQL)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $users      = $this->seedUsers();
            $categories = $this->seedCategories();
            $courses    = $this->seedCourses($users, $categories);

            // لكل كورس: فيديوهات + أسئلة فيديو + خيارات + امتحان نهائي
            $courseData = [];
            foreach ($courses as $course) {
                $courseData[$course['id']] = $this->seedCourseContent($course);
            }

            // تسجيلات الطلاب + تقدّم الدروس + إجابات أسئلة الفيديو + محاولات الامتحان + النتائج + الشهادات
            $this->seedStudentActivity($users, $courses, $courseData);

            // إشعارات متنوّعة لكل المستخدمين
            $this->seedNotifications($users, $courses);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->printSummary();
    }

    // ──────────────────────────────────────────────────────────────────────
    //  المستخدمون
    // ──────────────────────────────────────────────────────────────────────

    /** @return array{admins:int[], publishers:int[], students:int[], all:array} */
    private function seedUsers(): array
    {
        $now = now();
        $admins = $publishers = $students = [];

        // مستخدمو الحسابات الأساسية المعروفة (كلمة المرور للجميع: password123)
        $admins[] = $this->insertUser('Admin User', 'admin@example.com', 'admin', true, $now);
        $publishers[] = $this->insertUser('Publisher User', 'publisher@example.com', 'publisher', true, $now);
        $students[] = $this->insertUser('Normal User', 'user@example.com', 'user', true, $now);

        // ناشرون إضافيون
        foreach ([
            ['Sara Khalid', 'sara.publisher@example.com'],
            ['Yousef Adel', 'yousef.publisher@example.com'],
            ['Lina Hassan', 'lina.publisher@example.com'],
        ] as [$name, $email]) {
            $publishers[] = $this->insertUser($name, $email, 'publisher', true, $now);
        }

        // طلاب إضافيون (مجموعة كبيرة لإثراء البيانات)
        $studentNames = [
            'Ali Mansour', 'Mona Saleh', 'Omar Fadel', 'Huda Nabil', 'Karim Wael',
            'Nour Tariq', 'Rami Ziad', 'Dana Salim', 'Tarek Hadi', 'Reem Fares',
            'Sami Najjar', 'Lara Odeh', 'Fadi Bishara', 'Maya Sleiman', 'Jad Aoun',
        ];
        foreach ($studentNames as $i => $name) {
            $email = 'student'.($i + 1).'@example.com';
            $students[] = $this->insertUser($name, $email, 'user', true, $now);
        }

        // مستخدم معطّل (is_active = false) لتغطية الحالة
        $this->insertUser('Disabled User', 'disabled@example.com', 'user', false, $now);

        return [
            'admins'     => $admins,
            'publishers' => $publishers,
            'students'   => $students,
            'all'        => array_merge($admins, $publishers, $students),
        ];
    }

    private function insertUser(string $name, string $email, string $role, bool $active, Carbon $now): int
    {
        return DB::table('users')->insertGetId([
            'name'       => $name,
            'email'      => $email,
            'password'   => Hash::make('password123'),
            'role'       => $role,
            'is_active'  => $active,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  التصنيفات
    // ──────────────────────────────────────────────────────────────────────

    /** @return int[] */
    private function seedCategories(): array
    {
        $now = now();
        $ids = [];
        $categories = [
            ['Web Development', 'بناء تطبيقات الويب الحديثة بالواجهات والـ APIs.'],
            ['Mobile Development', 'تطوير تطبيقات الجوال الأصلية ومتعددة المنصّات.'],
            ['Data Science', 'تحليل البيانات وتعلّم الآلة والذكاء الاصطناعي.'],
            ['UI/UX Design', 'تصميم تجارب وواجهات استخدام احترافية.'],
            ['DevOps & Cloud', 'النشر والأتمتة والبنية السحابية.'],
            ['Cybersecurity', 'أمن المعلومات واختبار الاختراق والحماية.'],
        ];

        foreach ($categories as [$name, $description]) {
            $ids[] = DB::table('categories')->insertGetId([
                'name'        => $name,
                'description' => $description,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        return $ids;
    }

    // ──────────────────────────────────────────────────────────────────────
    //  الكورسات
    // ──────────────────────────────────────────────────────────────────────

    /** @return array<int,array{id:int,publisher_id:int,videos_count:int}> */
    private function seedCourses(array $users, array $categories): array
    {
        $now = now();
        $publishers = $users['publishers'];

        $definitions = [
            ['Laravel API Development', 'بناء واجهات REST API احترافية باستخدام Laravel وSanctum.'],
            ['Flutter from Zero to Hero', 'تطبيقات جوال متعددة المنصّات بـ Flutter وDart.'],
            ['Practical Machine Learning', 'تعلّم الآلة عملياً بـ Python وبيانات حقيقية.'],
            ['Modern React & Next.js', 'واجهات تفاعلية حديثة بـ React وNext.js.'],
            ['Figma for Product Designers', 'تصميم المنتجات الرقمية من الفكرة إلى النموذج.'],
            ['Docker & Kubernetes', 'الحاويات والتنسيق والنشر السحابي.'],
            ['Ethical Hacking Basics', 'أساسيات اختبار الاختراق الأخلاقي والحماية.'],
            ['Python for Beginners', 'مدخل عملي شامل إلى لغة Python.'],
        ];

        $courses = [];
        foreach ($definitions as $i => [$title, $description]) {
            $publisherId = $publishers[$i % count($publishers)];
            $categoryId  = $categories[$i % count($categories)];

            $id = DB::table('courses')->insertGetId([
                'title'        => $title,
                'description'  => $description,
                'thumbnail'    => $this->sharedThumbnail, // نفس الصورة لكل الكورسات
                'category_id'  => $categoryId,
                'publisher_id' => $publisherId,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            $courses[] = [
                'id'           => $id,
                'publisher_id' => $publisherId,
                'videos_count' => 3 + ($i % 3), // 3..5 فيديوهات لكل كورس
            ];
        }

        return $courses;
    }

    // ──────────────────────────────────────────────────────────────────────
    //  محتوى الكورس: فيديوهات + أسئلة فيديو + امتحان نهائي
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array{
     *   videos: array<int,array{id:int}>,
     *   video_questions: array<int,array{id:int,correct:int,wrong:int}>,
     *   exam_id: int,
     *   exam_questions: array<int,array{id:int,correct:int,wrong:int}>
     * }
     */
    private function seedCourseContent(array $course): array
    {
        $now = now();
        $courseId = $course['id'];

        $videos = [];
        $videoQuestions = [];

        for ($v = 1; $v <= $course['videos_count']; $v++) {
            $videoId = DB::table('course_videos')->insertGetId([
                'course_id'   => $courseId,
                'title'       => "الدرس {$v}",
                'description' => "شرح تفصيلي للدرس رقم {$v} في هذا الكورس.",
                'video_path'  => $this->sharedVideo['original'],
                'video_144p'  => $this->sharedVideo['144p'],
                'video_360p'  => $this->sharedVideo['360p'],
                'video_720p'  => $this->sharedVideo['720p'],
                'duration'    => 180 + ($v * 45),
                'order'       => $v,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $videos[] = ['id' => $videoId];

            // سؤالان داخل كل فيديو
            for ($q = 1; $q <= 2; $q++) {
                $questionId = DB::table('video_questions')->insertGetId([
                    'video_id'      => $videoId,
                    'question'      => "سؤال الدرس {$v} رقم {$q}؟",
                    'time_in_video' => $q * 40,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);

                [$correct, $wrong] = $this->insertFourOptions('video_question_options', 'question_id', $questionId, $now);
                $videoQuestions[] = ['id' => $questionId, 'correct' => $correct, 'wrong' => $wrong];
            }
        }

        // ── امتحان الكورس النهائي (course_exams) ──
        $examId = DB::table('course_exams')->insertGetId([
            'course_id'        => $courseId,
            'duration_minutes' => 30,
            'is_published'     => true,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        // عدد أسئلة ضمن النطاق المسموح (10..35) — نختار 12
        $examQuestions = [];
        for ($q = 1; $q <= 12; $q++) {
            $eqId = DB::table('exam_questions')->insertGetId([
                'course_exam_id' => $examId,
                'question'       => "سؤال الامتحان رقم {$q}؟",
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            [$correct, $wrong] = $this->insertFourOptions('exam_question_options', 'exam_question_id', $eqId, $now);
            $examQuestions[] = ['id' => $eqId, 'correct' => $correct, 'wrong' => $wrong];
        }

        return [
            'videos'          => $videos,
            'video_questions' => $videoQuestions,
            'exam_id'         => $examId,
            'exam_questions'  => $examQuestions,
        ];
    }

    /**
     * يُدرج 4 خيارات (الأول صحيح) ويعيد [correct_option_id, first_wrong_option_id].
     *
     * @return array{0:int,1:int}
     */
    private function insertFourOptions(string $table, string $fk, int $parentId, Carbon $now): array
    {
        $rows = [];
        for ($o = 1; $o <= 4; $o++) {
            $rows[] = [
                $fk           => $parentId,
                'option_text' => "الخيار {$o}",
                'is_correct'  => $o === 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }
        DB::table($table)->insert($rows);

        $correct = DB::table($table)->where($fk, $parentId)->where('is_correct', true)->value('id');
        $wrong   = DB::table($table)->where($fk, $parentId)->where('is_correct', false)->orderBy('id')->value('id');

        return [$correct, $wrong];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  نشاط الطلاب: تسجيل + تقدّم + إجابات + محاولات امتحان + نتائج + شهادات
    // ──────────────────────────────────────────────────────────────────────

    private function seedStudentActivity(array $users, array $courses, array $courseData): void
    {
        $now = now();
        $students = $users['students'];

        foreach ($students as $sIndex => $studentId) {
            // كل طالب يُسجَّل في مجموعة من الكورسات (نمط متنوّع)
            $enrolledCourses = $this->pickCoursesForStudent($courses, $sIndex);

            foreach ($enrolledCourses as $cIndex => $course) {
                $courseId = $course['id'];
                $data = $courseData[$courseId];

                // ── enrollments ──
                DB::table('enrollments')->insert([
                    'user_id'     => $studentId,
                    'course_id'   => $courseId,
                    'enrolled_at' => $now,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);

                // هل سيُكمل هذا الطالب هذا الكورس؟ (نسبة لإثراء الحالات)
                $completes = (($sIndex + $cIndex) % 3) !== 0;

                // كم فيديو سيُشاهده (المكمِّل يشاهد الكل، غيره جزءاً)
                $videos = $data['videos'];
                $videosToWatch = $completes ? $videos : array_slice($videos, 0, max(1, intdiv(count($videos), 2)));

                // ── lesson_progress ──
                foreach ($videosToWatch as $video) {
                    DB::table('lesson_progress')->insert([
                        'user_id'         => $studentId,
                        'course_video_id' => $video['id'],
                        'watched_at'      => $now,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ]);
                }

                // ── video_question_answers (auto-graded) ──
                // الطالب يجيب على نسبة من أسئلة الفيديو حسب إكماله للكورس
                foreach ($data['video_questions'] as $qIdx => $vq) {
                    // لا نعرف video_id من هنا مباشرة، لكن نوزّع الإجابات على نسبة من الأسئلة
                    // المكمِّل يجيب على الكل بشكل صحيح؛ غيره على جزء بمزيج صواب/خطأ
                    if (! $completes && $qIdx % 2 === 0) {
                        continue; // غير المكمِّل يترك بعض الأسئلة
                    }

                    $isCorrect = $completes ? true : (($sIndex + $qIdx) % 2 === 0);
                    DB::table('video_question_answers')->insert([
                        'user_id'     => $studentId,
                        'question_id' => $vq['id'],
                        'option_id'   => $isCorrect ? $vq['correct'] : $vq['wrong'],
                        'is_correct'  => $isCorrect,
                        'created_at'  => $now,
                    ]);
                }

                // ── exam_attempts + exam_attempt_answers (للمكمِّلين فقط) ──
                $examPercentage = 0.0;
                if ($completes) {
                    $examPercentage = $this->seedExamAttempt($studentId, $data, $sIndex, $now);
                }

                // ── course_results ──
                $lessonsPercentage = round((count($videosToWatch) / max(1, count($videos))) * 100, 2);
                $finalScore = round(($lessonsPercentage * 0.30) + ($examPercentage * 0.70), 2);

                DB::table('course_results')->insert([
                    'user_id'            => $studentId,
                    'course_id'          => $courseId,
                    'lessons_percentage' => $lessonsPercentage,
                    'exam_percentage'    => $examPercentage,
                    'final_score'        => $finalScore,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]);

                // ── course_certificates (للمكمِّلين الناجحين) ──
                if ($completes && $finalScore >= 50) {
                    $level = $finalScore >= 85 ? 'excellent' : ($finalScore >= 65 ? 'good' : 'average');
                    DB::table('course_certificates')->insert([
                        'user_id'          => $studentId,
                        'course_id'        => $courseId,
                        'certificate_code' => 'CERT-'.strtoupper(Str::random(8)),
                        'level'            => $level,
                        'issued_at'        => $now,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                }
            }
        }
    }

    /**
     * يُنشئ محاولة امتحان مع إجاباتها ويعيد نسبة النجاح (0..100).
     */
    private function seedExamAttempt(int $studentId, array $data, int $sIndex, Carbon $now): float
    {
        $examQuestions = $data['exam_questions'];
        $total = count($examQuestions);
        if ($total === 0) {
            return 0.0;
        }

        $attemptId = DB::table('exam_attempts')->insertGetId([
            'user_id'        => $studentId,
            'course_exam_id' => $data['exam_id'],
            'started_at'     => $now->copy()->subMinutes(25),
            'ends_at'        => $now->copy()->subMinutes(25)->addMinutes(30),
            'submitted_at'   => $now,
            'status'         => 'submitted',
            'score'          => 0, // يُحدَّث بعد حساب الإجابات
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        $correctCount = 0;
        foreach ($examQuestions as $qIdx => $eq) {
            // نمط لتنويع عدد الإجابات الصحيحة بين الطلاب (نجاح متفاوت)
            $isCorrect = (($sIndex + $qIdx) % 4) !== 0; // ~75% صحيحة
            if ($isCorrect) {
                $correctCount++;
            }

            DB::table('exam_attempt_answers')->insert([
                'exam_attempt_id'         => $attemptId,
                'exam_question_id'        => $eq['id'],
                'exam_question_option_id' => $isCorrect ? $eq['correct'] : $eq['wrong'],
                'is_correct'              => $isCorrect,
                'created_at'              => $now,
                'updated_at'              => $now,
            ]);
        }

        $percentage = round(($correctCount / $total) * 100, 2);
        DB::table('exam_attempts')->where('id', $attemptId)->update(['score' => $percentage]);

        return $percentage;
    }

    /**
     * يختار مجموعة كورسات لطالب بشكل متنوّع (3..5 كورسات).
     *
     * @return array<int,array>
     */
    private function pickCoursesForStudent(array $courses, int $sIndex): array
    {
        $count = count($courses);
        $take = 3 + ($sIndex % 3); // 3..5
        $picked = [];
        for ($k = 0; $k < $take; $k++) {
            $picked[] = $courses[($sIndex + $k) % $count];
        }

        return $picked;
    }

    // ──────────────────────────────────────────────────────────────────────
    //  الإشعارات
    // ──────────────────────────────────────────────────────────────────────

    private function seedNotifications(array $users, array $courses): void
    {
        $now = now();
        $rows = [];

        foreach ($users['all'] as $i => $userId) {
            $course = $courses[$i % count($courses)];

            $rows[] = [
                'user_id'    => $userId,
                'type'       => 'general',
                'title'      => 'مرحباً بك في EduQuest',
                'body'       => 'نتمنى لك رحلة تعلّم ممتعة ومثمرة.',
                'data'       => null,
                'read_at'    => $now, // مقروء
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $rows[] = [
                'user_id'    => $userId,
                'type'       => 'course',
                'title'      => 'تم نشر درس جديد',
                'body'       => 'أُضيف محتوى جديد إلى أحد كورساتك.',
                'data'       => json_encode(['course_id' => $course['id']], JSON_UNESCAPED_UNICODE),
                'read_at'    => null, // غير مقروء
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $rows[] = [
                'user_id'    => $userId,
                'type'       => 'exam',
                'title'      => 'تذكير بامتحان الكورس',
                'body'       => 'لا تنسَ إكمال امتحان الكورس قبل انتهاء المدة.',
                'data'       => json_encode(['course_id' => $course['id']], JSON_UNESCAPED_UNICODE),
                'read_at'    => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // إدراج جماعي على دفعات لتفادي الحدود
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('notifications')->insert($chunk);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    //  نسخ الملفات المشتركة (صورة واحدة + فيديو واحد)
    // ──────────────────────────────────────────────────────────────────────

    /** ينسخ الصورة الحقيقية مرّة واحدة إلى courses/thumbnails ويعيد مسارها النسبي. */
    private function copySharedThumbnail(\Illuminate\Contracts\Filesystem\Filesystem $disk): string
    {
        $relative = 'courses/thumbnails/seed_shared_thumbnail.png';

        if (is_file(self::SOURCE_IMAGE)) {
            $disk->put($relative, file_get_contents(self::SOURCE_IMAGE));
        } else {
            $this->command?->warn('⚠ تعذّر العثور على الصورة المصدر: '.self::SOURCE_IMAGE);
        }

        return $relative;
    }

    /**
     * ينسخ الفيديو الحقيقي مرّة واحدة إلى course-videos/shared، وكل الجودات تشير لنفس الملف.
     *
     * @return array<string,string> مفاتيحها: original, 144p, 360p, 720p
     */
    private function copySharedVideo(\Illuminate\Contracts\Filesystem\Filesystem $disk): array
    {
        $directory = 'course-videos/shared';
        $original  = "{$directory}/original.mp4";

        if (is_file(self::SOURCE_VIDEO)) {
            $disk->makeDirectory($directory);
            $disk->put($original, file_get_contents(self::SOURCE_VIDEO));
        } else {
            $this->command?->warn('⚠ تعذّر العثور على الفيديو المصدر: '.self::SOURCE_VIDEO);
        }

        // كل الجودات تشير لنفس الملف الفعلي (خفيف على القرص، يُعرض بشكل صحيح)
        return [
            'original' => $original,
            '144p'     => $original,
            '360p'     => $original,
            '720p'     => $original,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    //  ملخّص
    // ──────────────────────────────────────────────────────────────────────

    private function printSummary(): void
    {
        if (! $this->command) {
            return;
        }

        $tables = [
            'users', 'categories', 'courses', 'course_videos', 'video_questions',
            'video_question_options', 'video_question_answers', 'course_certificates',
            'enrollments', 'lesson_progress', 'course_exams', 'exam_questions',
            'exam_question_options', 'exam_attempts', 'exam_attempt_answers',
            'course_results', 'notifications',
        ];

        $this->command->info('✔ FullDatabaseSeeder: تمت تعبئة جميع الجداول.');
        foreach ($tables as $t) {
            $this->command->line(sprintf('   %-26s %d', $t, DB::table($t)->count()));
        }
        $this->command->info('ملاحظة: تأكّد أنّ APP_URL يطابق رابط الخادم لعرض الصور/الفيديوهات.');
    }
}
