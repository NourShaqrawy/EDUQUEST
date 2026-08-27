<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  MassiveDataSeeder — بيانات ضخمة تمثّل النظام بالكامل (لعرض/مناقشة محلية)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * يملأ كل جداول المنصّة ببيانات واقعية تغطّي **جميع الحالات**:
 *   • مستخدمون: admin/publisher/user + حساب معطّل (is_active=false).
 *   • كورسات بكل الحالات: pending/approved/rejected × ongoing/completed
 *     × certified(has_certificate=true)/introductory(false).
 *   • فيديوهات حقيقية قابلة للمشاهدة + نسخ 144/360/720 مولَّدة بـ FFmpeg،
 *     مع أسئلة داخل الفيديو وخياراتها (إجابة صحيحة واحدة).
 *   • امتحانات نهائية (بنك أسئلة 10..35) منشورة وغير منشورة + questions_to_serve.
 *   • محاولات امتحان بكل الحالات: in_progress / submitted / expired،
 *     مع exam_attempt_questions (بنك المحاولة) + exam_attempt_answers + النتائج.
 *   • سجل مراقبة ضخم (exam_attempt_events): الأنواع التسعة + terminated،
 *     موزّعة زمنياً على المحاولات (محور ميزة "مراقبة الامتحانات").
 *   • شهادات (average/good/excellent) للناجحين.
 *   • تسجيلات + تقدّم دروس + إجابات أسئلة الفيديو.
 *   • إشعارات متنوّعة (مقروءة/غير مقروءة، بمحتوى ثنائي اللغة في data).
 *   • أسئلة شائعة كثيرة (FaqSeeder + إضافات) + اقتراحات زوّار بحالاتها.
 *   • طلبات حذف كورسات (pending/approved/rejected).
 *
 * الوسائط: تُقرأ من  database/seeders/{images,videos}  (كل الملفات تلقائياً).
 *   - صورة اسمها "all" (أي امتداد) = الصورة الافتراضية عند غياب صورة الكورس.
 *   - تُنسخ الصور مرّة واحدة إلى storage/app/public/courses/thumbnails.
 *   - يُنسخ كل فيديو مرّة واحدة إلى storage/app/public/course-videos/shared،
 *     وتُولَّد له نسخ 144/360/720 بـ FFmpeg (إن توفّر؛ وإلا يشير للأصل).
 *   - المدة الحقيقية لكل فيديو تُقرأ عبر ffprobe (fallback: 10 ثوانٍ).
 *
 * التشغيل:  php artisan migrate:fresh --seed
 *      أو:  php artisan db:seed --class=MassiveDataSeeder   (على قاعدة فارغة)
 *
 * راجع  SEEDING.md  في جذر مشروع الباك-إند للتفاصيل الكاملة والضبط.
 */
class MassiveDataSeeder extends Seeder
{
    // ── ضبط الحجم (عدّلها لتكبير/تصغير مجموعة البيانات) ──────────────────────
    private const EXTRA_STUDENTS   = 60;   // طلاب إضافيون فوق الحسابات المعروفة
    private const EXTRA_PUBLISHERS = 12;   // ناشرون إضافيون
    private const EXTRA_ADMINS     = 3;    // مدراء إضافيون
    private const MIN_VIDEOS       = 3;    // أدنى عدد دروس لكل كورس
    private const MAX_VIDEOS       = 6;    // أقصى عدد دروس لكل كورس
    private const EXAM_BANK_MIN    = 12;   // حجم بنك أسئلة الامتحان (≥10، ≤35)
    private const EXAM_BANK_MAX    = 30;
    private const PASS_THRESHOLD   = 60.0; // نسبة النجاح لإصدار الشهادة

    private const PASSWORD = 'password123';

    /** disk('public') = storage/app/public (مربوط بـ public/storage). */
    private \Illuminate\Contracts\Filesystem\Filesystem $disk;

    /** المسار النسبي للصورة الافتراضية ("all"). */
    private ?string $defaultThumb = null;

    /** مسارات صور الكورسات النسبية (باستثناء الافتراضية). */
    private array $thumbs = [];

    /**
     * فيديوهات جاهزة: كل عنصر { paths:{video_path,video_144p,video_360p,video_720p}, duration:int }.
     * @var array<int,array>
     */
    private array $videos = [];

    /** عدّادات للملخّص النهائي. */
    private array $counts = [];

    public function run(): void
    {
        $this->disk = Storage::disk('public');

        $this->command?->info('▶ نسخ الوسائط (صور + فيديوهات) وتوليد الجودات…');
        $this->prepareThumbnails();
        $this->prepareVideos();

        if (empty($this->videos)) {
            $this->command?->warn('⚠ لا توجد فيديوهات في database/seeders/videos — سيتم إنشاء دروس بلا ملفات مشاهدة.');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->command?->info('▶ المستخدمون…');
            $users = $this->seedUsers();

            $this->command?->info('▶ التصنيفات…');
            $categories = $this->seedCategories();

            $this->command?->info('▶ الكورسات (كل الحالات) + محتواها…');
            [$courses, $courseData] = $this->seedCoursesAndContent($users, $categories);

            $this->command?->info('▶ نشاط الطلاب: تسجيل + تقدّم + إجابات + محاولات + مراقبة + نتائج + شهادات…');
            $this->seedStudentActivity($users, $courses, $courseData);

            $this->command?->info('▶ الإشعارات…');
            $this->seedNotifications($users, $courses);

            $this->command?->info('▶ طلبات حذف الكورسات…');
            $this->seedCourseDeleteRequests($users, $courses);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // الأسئلة الشائعة (الأساسية عبر FaqSeeder ثم إضافات + اقتراحات زوّار)
        $this->command?->info('▶ الأسئلة الشائعة + اقتراحات الزوّار…');
        $this->call(FaqSeeder::class);
        $this->seedExtraFaqs();
        $this->seedFaqSuggestions();
        $this->seedAppSettings();

        $this->printSummary();
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  الوسائط
    // ═══════════════════════════════════════════════════════════════════════

    private function assetsPath(string $sub): string
    {
        return database_path('seeders/'.$sub);
    }

    /** ينسخ كل الصور إلى thumbnails، ويحدّد الصورة الافتراضية "all". */
    private function prepareThumbnails(): void
    {
        $dir = $this->assetsPath('images');
        $this->disk->makeDirectory('courses/thumbnails');

        foreach (glob($dir.'/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [] as $file) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $relative = 'courses/thumbnails/seed_'.Str::slug($base).'.'.$ext;
            $this->disk->put($relative, file_get_contents($file));

            if (strtolower($base) === 'all') {
                $this->defaultThumb = $relative;
            } else {
                $this->thumbs[] = $relative;
            }
        }

        // إن لم توجد صورة "all"، استخدم أول صورة متاحة كافتراضية.
        if ($this->defaultThumb === null && ! empty($this->thumbs)) {
            $this->defaultThumb = $this->thumbs[0];
        }
    }

    /** ينسخ كل فيديو ويولّد نسخ 144/360/720 بـ FFmpeg، ويقرأ المدة الحقيقية. */
    private function prepareVideos(): void
    {
        $dir = $this->assetsPath('videos');
        $this->disk->makeDirectory('course-videos/shared');
        $ffmpeg = $this->findBinary('ffmpeg');

        foreach (glob($dir.'/*.{mp4,mov,avi,mkv,webm}', GLOB_BRACE) ?: [] as $i => $file) {
            $slug     = 'v'.($i + 1);
            $original = "course-videos/shared/{$slug}_original.mp4";
            $this->disk->put($original, file_get_contents($file));
            $absOriginal = $this->disk->path($original);

            $paths = [
                'video_path' => $original,
                'video_144p' => $original,
                'video_360p' => $original,
                'video_720p' => $original,
            ];

            if ($ffmpeg) {
                foreach (['144p' => 144, '360p' => 360, '720p' => 720] as $label => $height) {
                    $rel = "course-videos/shared/{$slug}_{$label}.mp4";
                    $abs = $this->disk->path($rel);
                    // scale=-2:H يحافظ على النسبة ويضمن أبعاداً زوجية.
                    $cmd = sprintf(
                        '"%s" -y -i "%s" -vf scale=-2:%d -c:v libx264 -preset veryfast -crf 28 -c:a aac -b:a 96k "%s"',
                        $ffmpeg, $absOriginal, $height, $abs
                    );
                    @exec($cmd.' 2>&1', $out, $code);
                    if ($code === 0 && is_file($abs)) {
                        $paths['video_'.$label] = $rel;
                    }
                }
            }

            $this->videos[] = [
                'paths'    => $paths,
                'duration' => $this->probeDuration($absOriginal),
            ];
        }
    }

    /** يقرأ مدة الفيديو بالثواني عبر ffprobe (fallback = 10). */
    private function probeDuration(string $absPath): int
    {
        $ffprobe = $this->findBinary('ffprobe');
        if ($ffprobe) {
            $cmd = sprintf('"%s" -v error -show_entries format=duration -of csv=p=0 "%s"', $ffprobe, $absPath);
            $val = @shell_exec($cmd);
            $secs = (int) round((float) trim((string) $val));
            if ($secs > 0) {
                return $secs;
            }
        }

        return 10;
    }

    /** يبحث عن ffmpeg/ffprobe في PATH (يعمل على Windows/Unix). */
    private function findBinary(string $name): ?string
    {
        $probe = stripos(PHP_OS, 'WIN') === 0 ? "where {$name}" : "command -v {$name}";
        $out = @shell_exec($probe);
        if ($out) {
            $first = trim(strtok($out, "\n"));
            if ($first !== '') {
                return $first;
            }
        }

        return null;
    }

    /** يعيد صورة كورس (دورياً) أو الافتراضية. */
    private function pickThumb(int $index): ?string
    {
        if (empty($this->thumbs)) {
            return $this->defaultThumb;
        }

        return $this->thumbs[$index % count($this->thumbs)];
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  المستخدمون
    // ═══════════════════════════════════════════════════════════════════════

    /** @return array{admins:int[],publishers:int[],students:int[],disabled:int,all:int[]} */
    private function seedUsers(): array
    {
        $now = now();
        $admins = $publishers = $students = [];

        // الحسابات المعروفة (كلمة المرور للجميع: password123)
        $admins[]     = $this->insertUser('Admin User', 'admin@example.com', 'admin', true, $now);
        $publishers[] = $this->insertUser('Publisher User', 'publisher@example.com', 'publisher', true, $now);
        $students[]   = $this->insertUser('Normal User', 'user@example.com', 'user', true, $now);

        for ($i = 1; $i <= self::EXTRA_ADMINS; $i++) {
            $admins[] = $this->insertUser("Admin {$i}", "admin{$i}@example.com", 'admin', true, $now);
        }

        $publisherNames = ['Sara Khalid','Yousef Adel','Lina Hassan','Omar Nasser','Rana Fouad',
            'Khalil Amin','Dima Saad','Nabil Rashid','Hala Zayn','Fares Diab','Maya Habib','Tarek Sami'];
        for ($i = 0; $i < self::EXTRA_PUBLISHERS; $i++) {
            $name = $publisherNames[$i] ?? ('Publisher '.($i + 1));
            $publishers[] = $this->insertUser($name, 'pub'.($i + 1).'@example.com', 'publisher', true, $now);
        }

        $studentFirst = ['Ali','Mona','Omar','Huda','Karim','Nour','Rami','Dana','Tarek','Reem',
            'Sami','Lara','Fadi','Maya','Jad','Sana','Bilal','Ruba','Ziad','Aya','Hadi','Salma',
            'Wael','Nada','Basel','Rima','Adel','Lama','Marwan','Dalia'];
        $studentLast = ['Mansour','Saleh','Fadel','Nabil','Wael','Tariq','Ziad','Salim','Hadi','Fares',
            'Najjar','Odeh','Bishara','Sleiman','Aoun','Khoury','Haddad','Sabbagh','Karam','Nasr'];
        for ($i = 0; $i < self::EXTRA_STUDENTS; $i++) {
            $name = $studentFirst[$i % count($studentFirst)].' '.$studentLast[$i % count($studentLast)];
            $students[] = $this->insertUser($name, 'student'.($i + 1).'@example.com', 'user', true, $now);
        }

        // حساب معطّل لتغطية الحالة
        $disabled = $this->insertUser('Disabled User', 'disabled@example.com', 'user', false, $now);

        return [
            'admins'     => $admins,
            'publishers' => $publishers,
            'students'   => $students,
            'disabled'   => $disabled,
            'all'        => array_merge($admins, $publishers, $students, [$disabled]),
        ];
    }

    private function insertUser(string $name, string $email, string $role, bool $active, Carbon $now): int
    {
        $this->bump('users');

        return DB::table('users')->insertGetId([
            'name'       => $name,
            'email'      => $email,
            'password'   => Hash::make(self::PASSWORD),
            'role'       => $role,
            'is_active'  => $active,
            'theme'      => 'light',
            'language'   => $role === 'user' ? 'ar' : 'en',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  التصنيفات
    // ═══════════════════════════════════════════════════════════════════════

    /** @return int[] */
    private function seedCategories(): array
    {
        $now = now();
        $ids = [];
        foreach ([
            ['Web Development', 'بناء تطبيقات الويب الحديثة بالواجهات والـ APIs.'],
            ['Mobile Development', 'تطوير تطبيقات الجوال الأصلية ومتعددة المنصّات.'],
            ['Data Science', 'تحليل البيانات وتعلّم الآلة والذكاء الاصطناعي.'],
            ['UI/UX Design', 'تصميم تجارب وواجهات استخدام احترافية.'],
            ['DevOps & Cloud', 'النشر والأتمتة والبنية السحابية.'],
            ['Cybersecurity', 'أمن المعلومات واختبار الاختراق والحماية.'],
            ['Business & Marketing', 'ريادة الأعمال والتسويق الرقمي.'],
            ['Languages', 'تعلّم اللغات ومهارات التواصل.'],
        ] as [$name, $desc]) {
            $this->bump('categories');
            $ids[] = DB::table('categories')->insertGetId([
                'name' => $name, 'description' => $desc, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        return $ids;
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  الكورسات + المحتوى (كل الحالات)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @return array{0:array<int,array>,1:array<int,array>}  [courses, courseData keyed by course id]
     */
    private function seedCoursesAndContent(array $users, array $categories): array
    {
        $now = now();
        $publishers = $users['publishers'];

        // (title, description) — سنغطّي بها مصفوفة الحالات أدناه دورياً.
        $titles = [
            ['Laravel API Development', 'بناء واجهات REST API احترافية باستخدام Laravel وSanctum.'],
            ['Flutter from Zero to Hero', 'تطبيقات جوال متعددة المنصّات بـ Flutter وDart.'],
            ['Practical Machine Learning', 'تعلّم الآلة عملياً بـ Python وبيانات حقيقية.'],
            ['Modern React & Next.js', 'واجهات تفاعلية حديثة بـ React وNext.js.'],
            ['Figma for Product Designers', 'تصميم المنتجات الرقمية من الفكرة إلى النموذج.'],
            ['Docker & Kubernetes', 'الحاويات والتنسيق والنشر السحابي.'],
            ['Ethical Hacking Basics', 'أساسيات اختبار الاختراق الأخلاقي والحماية.'],
            ['Python for Beginners', 'مدخل عملي شامل إلى لغة Python.'],
            ['Advanced CSS & Tailwind', 'تصميم واجهات متجاوبة بأحدث تقنيات CSS.'],
            ['Node.js Backend Mastery', 'خوادم عالية الأداء بـ Node.js وExpress.'],
            ['SQL & Database Design', 'تصميم قواعد البيانات والاستعلامات المتقدّمة.'],
            ['Digital Marketing 101', 'أساسيات التسويق الرقمي وإدارة الحملات.'],
            ['Git & GitHub Workflow', 'إدارة الإصدارات والعمل الجماعي باحتراف.'],
            ['TypeScript Deep Dive', 'إتقان TypeScript للمشاريع الكبيرة.'],
            ['Cloud with AWS', 'بناء ونشر التطبيقات على AWS.'],
            ['UX Research Methods', 'أبحاث المستخدم واختبار قابلية الاستخدام.'],
            ['Spoken English Fluency', 'تطوير الطلاقة في المحادثة الإنجليزية.'],
            ['Data Visualization', 'عرض البيانات بصرياً بأدوات حديثة.'],
            ['Vue.js Essentials', 'بناء تطبيقات تفاعلية بـ Vue 3.'],
            ['Cybersecurity Defense', 'الدفاع السيبراني ومراقبة الشبكات.'],
            ['Product Management', 'إدارة المنتجات الرقمية من الفكرة للإطلاق.'],
            ['Intro to AI', 'مقدّمة تطبيقية في الذكاء الاصطناعي.'],
            ['REST vs GraphQL', 'مقارنة عملية بين معماريتَي الـ APIs.'],
            ['Photography Basics', 'أساسيات التصوير الفوتوغرافي والإضاءة.'],
        ];

        // مصفوفة الحالات — تغطّي كل التركيبات المطلوبة للعرض.
        // [status, completion_status, has_certificate, publishedExam]
        $stateMatrix = [
            ['approved', 'completed', true,  true],   // معتمد مكتمل بشهادة (يظهر للطلاب + امتحان)
            ['approved', 'completed', true,  true],
            ['approved', 'completed', true,  true],
            ['approved', 'completed', false, false],  // معتمد مكتمل تعريفي (بلا امتحان)
            ['approved', 'completed', false, false],
            ['approved', 'ongoing',   true,  false],  // معتمد لكن قيد التعديل (مخفي عن الكتالوج)
            ['approved', 'ongoing',   true,  true],
            ['pending',  'ongoing',   true,  false],  // بانتظار موافقة الأدمن
            ['pending',  'ongoing',   true,  false],
            ['pending',  'ongoing',   false, false],
            ['rejected', 'ongoing',   true,  false],  // مرفوض (مع سبب)
            ['rejected', 'ongoing',   false, false],
        ];

        $rejectionReasons = [
            'المحتوى غير مكتمل ويحتاج مزيداً من الدروس التفصيلية.',
            'جودة الفيديوهات منخفضة، يُرجى إعادة الرفع بدقة أعلى.',
            'وصف الكورس لا يعكس محتواه الفعلي.',
        ];

        $courses = [];
        $courseData = [];

        foreach ($titles as $i => [$title, $desc]) {
            $state = $stateMatrix[$i % count($stateMatrix)];
            [$status, $completion, $hasCert, $publishExam] = $state;
            $publisherId = $publishers[$i % count($publishers)];
            $categoryId  = $categories[$i % count($categories)];

            // بعض الكورسات بلا صورة → تستخدم الافتراضية "all".
            $thumb = ($i % 5 === 4) ? $this->defaultThumb : $this->pickThumb($i);

            $this->bump('courses');
            $courseId = DB::table('courses')->insertGetId([
                'title'             => $title,
                'description'       => $desc.' يشمل الكورس أمثلة عملية وتمارين تفاعلية ومشروعاً ختامياً.',
                'thumbnail'         => $thumb,
                'status'            => $status,
                'has_certificate'   => $hasCert,
                'completion_status' => $completion,
                'rejection_reason'  => $status === 'rejected' ? $rejectionReasons[$i % count($rejectionReasons)] : null,
                'category_id'       => $categoryId,
                'publisher_id'      => $publisherId,
                'created_at'        => $now->copy()->subDays(60 - $i),
                'updated_at'        => $now,
            ]);

            $course = [
                'id'           => $courseId,
                'publisher_id' => $publisherId,
                'status'       => $status,
                'completion'   => $completion,
                'has_cert'     => $hasCert,
                'visible'      => $status === 'approved' && $completion === 'completed',
            ];
            $courses[] = $course;

            $courseData[$courseId] = $this->seedCourseContent($course, $publishExam, $i);
        }

        return [$courses, $courseData];
    }

    /**
     * فيديوهات + أسئلتها، وامتحان نهائي (إن كان الكورس بشهادة).
     * @return array{videos:array,video_questions:array,exam_id:?int,questions_to_serve:int,exam_questions:array}
     */
    private function seedCourseContent(array $course, bool $publishExam, int $seed): array
    {
        $now = now();
        $courseId = $course['id'];
        $videosCount = self::MIN_VIDEOS + ($seed % (self::MAX_VIDEOS - self::MIN_VIDEOS + 1));

        $videos = [];
        $videoQuestions = [];

        for ($v = 1; $v <= $videosCount; $v++) {
            $media = empty($this->videos) ? null : $this->videos[($seed + $v) % count($this->videos)];
            $duration = $media['duration'] ?? 10;

            $this->bump('course_videos');
            $videoId = DB::table('course_videos')->insertGetId(array_merge([
                'course_id'   => $courseId,
                'title'       => "الدرس {$v}",
                'description' => "شرح تفصيلي للدرس رقم {$v}: المفاهيم الأساسية مع تطبيق عملي.",
                'duration'    => $duration,
                'order'       => $v,
                'created_at'  => $now,
                'updated_at'  => $now,
            ], $media['paths'] ?? [
                'video_path' => null, 'video_144p' => null, 'video_360p' => null, 'video_720p' => null,
            ]));
            $videos[] = ['id' => $videoId];

            // سؤالان داخل الفيديو، بتوقيت ضمن المدة الحقيقية.
            $qCount = 2;
            for ($q = 1; $q <= $qCount; $q++) {
                $timeIn = max(1, (int) floor($duration * $q / ($qCount + 1)));
                $this->bump('video_questions');
                $questionId = DB::table('video_questions')->insertGetId([
                    'video_id'      => $videoId,
                    'question'      => "ما المفهوم الأساسي في الجزء {$q} من الدرس {$v}؟",
                    'time_in_video' => $timeIn,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
                [$correct, $wrong] = $this->insertFourOptions('video_question_options', 'question_id', $questionId, $now);
                $videoQuestions[] = ['id' => $questionId, 'video_id' => $videoId, 'correct' => $correct, 'wrong' => $wrong];
            }
        }

        // امتحان نهائي فقط للكورسات بشهادة (introductory بلا امتحان).
        $examId = null;
        $examQuestions = [];
        $questionsToServe = 10;

        if ($course['has_cert']) {
            $bankSize = self::EXAM_BANK_MIN + ($seed % (self::EXAM_BANK_MAX - self::EXAM_BANK_MIN + 1));
            $questionsToServe = min(10 + ($seed % 6), $bankSize); // 10..15، ≤ حجم البنك

            $this->bump('course_exams');
            $examId = DB::table('course_exams')->insertGetId([
                'course_id'          => $courseId,
                'duration_minutes'   => 20 + ($seed % 4) * 10, // 20..50
                'questions_to_serve' => $questionsToServe,
                'is_published'       => $publishExam,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            for ($q = 1; $q <= $bankSize; $q++) {
                $this->bump('exam_questions');
                $eqId = DB::table('exam_questions')->insertGetId([
                    'course_exam_id' => $examId,
                    'question'       => "سؤال الامتحان رقم {$q}: اختر الإجابة الصحيحة.",
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                [$correct, $wrong] = $this->insertFourOptions('exam_question_options', 'exam_question_id', $eqId, $now);
                $examQuestions[] = ['id' => $eqId, 'correct' => $correct, 'wrong' => $wrong];
            }
        }

        return [
            'videos'             => $videos,
            'video_questions'    => $videoQuestions,
            'exam_id'            => $examId,
            'is_published'       => $publishExam,
            'questions_to_serve' => $questionsToServe,
            'exam_questions'     => $examQuestions,
        ];
    }

    /**
     * يُدرج 4 خيارات (الأول صحيح) ويعيد [correct_id, first_wrong_id].
     * @return array{0:int,1:int}
     */
    private function insertFourOptions(string $table, string $fk, int $parentId, Carbon $now): array
    {
        $texts = ['الإجابة الصحيحة', 'إجابة خاطئة أولى', 'إجابة خاطئة ثانية', 'إجابة خاطئة ثالثة'];
        $rows = [];
        foreach ($texts as $o => $text) {
            $rows[] = [
                $fk => $parentId, 'option_text' => $text, 'is_correct' => $o === 0,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table($table)->insert($rows);
        $this->bump($table, 4);

        $correct = DB::table($table)->where($fk, $parentId)->where('is_correct', true)->value('id');
        $wrong   = DB::table($table)->where($fk, $parentId)->where('is_correct', false)->orderBy('id')->value('id');

        return [$correct, $wrong];
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  نشاط الطلاب
    // ═══════════════════════════════════════════════════════════════════════

    private function seedStudentActivity(array $users, array $courses, array $courseData): void
    {
        $now = now();
        $students = $users['students'];

        // نُسجّل الطلاب فقط في الكورسات المرئية (approved + completed).
        $visibleCourses = array_values(array_filter($courses, fn ($c) => $c['visible']));
        if (empty($visibleCourses)) {
            return;
        }

        foreach ($students as $sIndex => $studentId) {
            $take = 3 + ($sIndex % 4); // 3..6 كورسات لكل طالب
            for ($k = 0; $k < $take; $k++) {
                $course = $visibleCourses[($sIndex + $k) % count($visibleCourses)];
                $courseId = $course['id'];
                $data = $courseData[$courseId];

                // enrollment
                $this->bump('enrollments');
                DB::table('enrollments')->insert([
                    'user_id' => $studentId, 'course_id' => $courseId,
                    'enrolled_at' => $now->copy()->subDays(20), 'created_at' => $now, 'updated_at' => $now,
                ]);

                $completes = (($sIndex + $k) % 3) !== 0; // ~66% يكملون
                $videos = $data['videos'];
                $watched = $completes ? $videos : array_slice($videos, 0, max(1, intdiv(count($videos), 2)));

                foreach ($watched as $video) {
                    $this->bump('lesson_progress');
                    DB::table('lesson_progress')->insert([
                        'user_id' => $studentId, 'course_video_id' => $video['id'],
                        'watched_at' => $now->copy()->subDays(15), 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }

                // إجابات أسئلة الفيديو (للدروس المشاهَدة فقط)
                $watchedIds = array_column($watched, 'id');
                foreach ($data['video_questions'] as $qIdx => $vq) {
                    if (! in_array($vq['video_id'], $watchedIds, true)) {
                        continue;
                    }
                    if (! $completes && $qIdx % 2 === 0) {
                        continue; // غير المكمِّل يترك بعض الأسئلة
                    }
                    $isCorrect = $completes ? (($sIndex + $qIdx) % 5 !== 0) : (($sIndex + $qIdx) % 2 === 0);
                    $this->bump('video_question_answers');
                    DB::table('video_question_answers')->insert([
                        'user_id' => $studentId, 'question_id' => $vq['id'],
                        'option_id' => $isCorrect ? $vq['correct'] : $vq['wrong'],
                        'is_correct' => $isCorrect, 'created_at' => $now->copy()->subDays(14),
                    ]);
                }

                // محاولة امتحان (فقط لكورسات الشهادة ذات الامتحان)
                $examPct = 0.0;
                $hasExam = $data['exam_id'] !== null && ! empty($data['exam_questions']);
                if ($hasExam && $completes) {
                    $examPct = $this->seedExamAttempt($studentId, $data, $sIndex, $k, $now);
                }

                // نتيجة الكورس
                $lessonsPct = round((count($watched) / max(1, count($videos))) * 100, 2);
                $finalScore = $hasExam
                    ? round(($lessonsPct * 0.30) + ($examPct * 0.70), 2)
                    : $lessonsPct; // التعريفي: النتيجة = نسبة الدروس فقط

                $this->bump('course_results');
                DB::table('course_results')->insert([
                    'user_id' => $studentId, 'course_id' => $courseId,
                    'lessons_percentage' => $lessonsPct, 'exam_percentage' => $examPct,
                    'final_score' => $finalScore, 'created_at' => $now, 'updated_at' => $now,
                ]);

                // شهادة (فقط لكورسات الشهادة والناجحين)
                if ($course['has_cert'] && $completes && $finalScore >= self::PASS_THRESHOLD) {
                    $level = $finalScore >= 85 ? 'excellent' : ($finalScore >= 70 ? 'good' : 'average');
                    $this->bump('course_certificates');
                    DB::table('course_certificates')->insert([
                        'user_id' => $studentId, 'course_id' => $courseId,
                        'certificate_code' => 'CERT-'.strtoupper(Str::random(10)),
                        'level' => $level, 'issued_at' => $now->copy()->subDays(5),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    /**
     * محاولة امتحان كاملة: بنك المحاولة (exam_attempt_questions) + إجابات + أحداث مراقبة + status متنوّع.
     * يعيد نسبة الامتحان (0..100).
     */
    private function seedExamAttempt(int $studentId, array $data, int $sIndex, int $k, Carbon $now): float
    {
        $bank = $data['exam_questions'];
        $serve = min($data['questions_to_serve'], count($bank));

        // نختار مجموعة فرعية عشوائية-حتمية من البنك لهذه المحاولة.
        $offset = ($sIndex + $k) % max(1, count($bank));
        $served = [];
        for ($j = 0; $j < $serve; $j++) {
            $served[] = $bank[($offset + $j) % count($bank)];
        }

        // status متنوّع: معظمها submitted، بعضها expired، وقليل in_progress.
        $roll = ($sIndex * 7 + $k) % 10;
        if ($roll === 0) {
            $status = 'in_progress';
        } elseif ($roll === 1) {
            $status = 'expired';
        } else {
            $status = 'submitted';
        }

        $startedAt = $now->copy()->subDays(3)->subMinutes(40);
        $durationMin = 30;
        $endsAt = $startedAt->copy()->addMinutes($durationMin);
        $submittedAt = $status === 'in_progress' ? null : $startedAt->copy()->addMinutes(min($durationMin, 22));

        $this->bump('exam_attempts');
        $attemptId = DB::table('exam_attempts')->insertGetId([
            'user_id' => $studentId, 'course_exam_id' => $data['exam_id'],
            'started_at' => $startedAt, 'ends_at' => $endsAt, 'submitted_at' => $submittedAt,
            'status' => $status, 'score' => 0, 'created_at' => $startedAt, 'updated_at' => $now,
        ]);

        // بنك المحاولة
        $aqRows = [];
        foreach ($served as $order => $eq) {
            $aqRows[] = ['exam_attempt_id' => $attemptId, 'exam_question_id' => $eq['id'], 'display_order' => $order + 1];
        }
        DB::table('exam_attempt_questions')->insert($aqRows);
        $this->bump('exam_attempt_questions', count($aqRows));

        // الإجابات (in_progress: يجيب على جزء فقط)
        $answerable = $status === 'in_progress' ? array_slice($served, 0, max(1, intdiv($serve, 2))) : $served;
        $correctCount = 0;
        foreach ($answerable as $qIdx => $eq) {
            $isCorrect = (($sIndex + $qIdx) % 4) !== 0; // ~75%
            if ($isCorrect) {
                $correctCount++;
            }
            $this->bump('exam_attempt_answers');
            DB::table('exam_attempt_answers')->insert([
                'exam_attempt_id' => $attemptId, 'exam_question_id' => $eq['id'],
                'exam_question_option_id' => $isCorrect ? $eq['correct'] : $eq['wrong'],
                'is_correct' => $isCorrect, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // النسبة تُحسب على عدد الأسئلة المخدومة (غير المُجاب = خطأ).
        $percentage = $status === 'in_progress' ? 0.0 : round(($correctCount / max(1, $serve)) * 100, 2);
        DB::table('exam_attempts')->where('id', $attemptId)->update(['score' => $percentage]);

        // ── سجل أحداث المراقبة (ضخم ومتنوّع) ──
        $this->seedProctoringEvents($attemptId, $sIndex, $k, $startedAt, $status);

        return $percentage;
    }

    /**
     * يولّد أحداث مراقبة واقعية موزّعة زمنياً على المحاولة.
     * الأنواع مطابقة لـ proctoring.js → EXAM_EVENT_TYPES (+ terminated).
     * بعض المحاولات "نظيفة"، وبعضها فيه مخالفات، وقليل يُنهى بـ terminated.
     */
    private function seedProctoringEvents(int $attemptId, int $sIndex, int $k, Carbon $startedAt, string $status): void
    {
        $benign = ['tab_focus', 'fullscreen_enter', 'question_reveal'];
        $violations = ['tab_blur', 'fullscreen_exit', 'copy_attempt', 'cut_attempt', 'paste_attempt', 'context_menu'];

        $profile = ($sIndex + $k) % 4; // 0=نظيف، 1=خفيف، 2=كثيف، 3=منتهي بمخالفة
        $rows = [];
        $t = $startedAt->copy();

        // دائماً: دخول ملء الشاشة + كشف الأسئلة (سلوك طبيعي)
        $rows[] = $this->eventRow($attemptId, 'fullscreen_enter', null, $t->copy()->addSeconds(2));
        $revealCount = 5 + (($sIndex + $k) % 6);
        for ($r = 0; $r < $revealCount; $r++) {
            $rows[] = $this->eventRow($attemptId, 'question_reveal', ['index' => $r + 1], $t->copy()->addSeconds(10 + $r * 20));
        }

        if ($profile >= 1) {
            $vCount = $profile === 1 ? (2 + $sIndex % 3) : (8 + $sIndex % 10); // خفيف مقابل كثيف
            for ($e = 0; $e < $vCount; $e++) {
                $type = $violations[($sIndex + $e) % count($violations)];
                $ts = $t->copy()->addSeconds(30 + $e * 15);
                $meta = null;
                if ($type === 'fullscreen_exit') {
                    $meta = ['count' => $e + 1];
                    // كل خروج يتبعه عودة (طبيعي)
                    $rows[] = $this->eventRow($attemptId, 'fullscreen_exit', $meta, $ts);
                    $rows[] = $this->eventRow($attemptId, 'fullscreen_enter', null, $ts->copy()->addSeconds(3));
                    continue;
                }
                if ($type === 'tab_blur') {
                    $rows[] = $this->eventRow($attemptId, 'tab_blur', null, $ts);
                    $rows[] = $this->eventRow($attemptId, 'tab_focus', null, $ts->copy()->addSeconds(4));
                    continue;
                }
                $rows[] = $this->eventRow($attemptId, $type, $meta, $ts);
            }
        }

        // profile 3: إنهاء قسري بمخالفة (auto_submit + terminated)
        if ($profile === 3 && $status !== 'in_progress') {
            $ts = $t->copy()->addMinutes(15);
            $rows[] = $this->eventRow($attemptId, 'fullscreen_exit', ['count' => 99], $ts);
            $rows[] = $this->eventRow($attemptId, 'auto_submit', ['reason' => 'fullscreen_exit'], $ts->copy()->addSeconds(1));
            $rows[] = $this->eventRow($attemptId, 'terminated', ['reason' => 'fullscreen_exit'], $ts->copy()->addSeconds(2));
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('exam_attempt_events')->insert($chunk);
        }
        $this->bump('exam_attempt_events', count($rows));
    }

    private function eventRow(int $attemptId, string $type, ?array $meta, Carbon $ts): array
    {
        return [
            'exam_attempt_id' => $attemptId,
            'type'            => $type,
            'meta'            => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'client_at'       => $ts->copy()->format('Y-m-d H:i:s'),
            'created_at'      => $ts->copy()->format('Y-m-d H:i:s'),
            'updated_at'      => $ts->copy()->format('Y-m-d H:i:s'),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  الإشعارات
    // ═══════════════════════════════════════════════════════════════════════

    private function seedNotifications(array $users, array $courses): void
    {
        $now = now();
        $rows = [];

        $templates = [
            ['general', 'مرحباً بك في EduQuest', 'نتمنى لك رحلة تعلّم ممتعة ومثمرة.', true],
            ['course',  'تم نشر درس جديد', 'أُضيف محتوى جديد إلى أحد كورساتك.', false],
            ['exam',    'تذكير بامتحان الكورس', 'لا تنسَ إكمال امتحان الكورس قبل انتهاء المدة.', false],
            ['certificate', 'تهانينا! حصلت على شهادة', 'تم إصدار شهادتك بعد اجتياز الكورس.', false],
        ];

        foreach ($users['all'] as $i => $userId) {
            $course = $courses[$i % count($courses)];
            foreach ($templates as $tIdx => [$type, $title, $body, $read]) {
                // ننوّع: ليس كل مستخدم يحصل على كل نوع
                if (($i + $tIdx) % 2 === 1 && $type !== 'general') {
                    continue;
                }
                $rows[] = [
                    'user_id' => $userId, 'type' => $type, 'title' => $title, 'body' => $body,
                    'data' => json_encode([
                        'course_id' => $course['id'],
                        'title_en'  => $title, 'title_ar' => $title,
                        'body_en'   => $body,  'body_ar'  => $body,
                    ], JSON_UNESCAPED_UNICODE),
                    'read_at' => $read ? $now : null,
                    'created_at' => $now->copy()->subHours($tIdx), 'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('notifications')->insert($chunk);
        }
        $this->bump('notifications', count($rows));
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  طلبات حذف الكورسات
    // ═══════════════════════════════════════════════════════════════════════

    private function seedCourseDeleteRequests(array $users, array $courses): void
    {
        $now = now();
        $statuses = ['pending', 'approved', 'rejected'];
        $reasons = [
            'هذا الكورس قديم ولم يعد محتواه محدّثاً.',
            'أرغب بإعادة إنشائه بمحتوى مختلف تماماً.',
            'تكرار مع كورس آخر لدي.',
        ];

        // نأخذ 6 كورسات ونربط كل طلب بمالكها الفعلي (publisher_id).
        $picked = array_slice($courses, 0, 6);
        foreach ($picked as $i => $course) {
            $status = $statuses[$i % count($statuses)];
            $this->bump('course_delete_requests');
            DB::table('course_delete_requests')->insert([
                'course_id'   => $course['id'],
                'publisher_id'=> $course['publisher_id'],
                'reason'      => $reasons[$i % count($reasons)],
                'status'      => $status,
                'admin_note'  => $status === 'rejected' ? 'الكورس نشط ولديه طلاب مسجّلون.' : null,
                'created_at'  => $now->copy()->subDays($i + 1),
                'updated_at'  => $now,
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  الأسئلة الشائعة الإضافية + اقتراحات الزوّار + الإعدادات
    // ═══════════════════════════════════════════════════════════════════════

    private function seedExtraFaqs(): void
    {
        $now = now();
        $order = (int) DB::table('faqs')->max('sort_order') + 1;

        $faqs = [
            ['Is the platform really free?', 'هل المنصّة مجانية فعلاً؟',
             'Yes, all courses are completely free to browse and learn.', 'نعم، جميع الكورسات مجانية بالكامل للتصفّح والتعلّم.'],
            ['How do I become a publisher?', 'كيف أصبح ناشراً؟',
             'Publisher accounts are created by the admin. Contact us to request one.', 'حسابات الناشرين يُنشئها المدير. تواصل معنا لطلب حساب.'],
            ['Can I download the videos?', 'هل يمكنني تنزيل الفيديوهات؟',
             'Videos stream within the platform and are not downloadable.', 'الفيديوهات تُعرض داخل المنصّة وغير قابلة للتنزيل.'],
            ['What happens if I fail the exam?', 'ماذا يحدث إن رسبت في الامتحان؟',
             'You can review the lessons; certificates require 60% or above.', 'يمكنك مراجعة الدروس؛ الشهادة تتطلّب 60% فأكثر.'],
            ['How is the final score calculated?', 'كيف تُحسب الدرجة النهائية؟',
             'Lessons weigh 30% and the exam 70% of the final score.', 'الدروس بوزن 30% والامتحان 70% من الدرجة النهائية.'],
            ['Is there anti-cheating during exams?', 'هل توجد مراقبة ضد الغش أثناء الامتحان؟',
             'Yes, exams run in fullscreen with proctoring event logging.', 'نعم، الامتحانات تعمل بملء الشاشة مع تسجيل أحداث المراقبة.'],
            ['Can I retake a course?', 'هل يمكنني إعادة الكورس؟',
             'You can rewatch lessons anytime; the exam is a single attempt.', 'يمكنك إعادة مشاهدة الدروس في أي وقت؛ الامتحان محاولة واحدة.'],
            ['Which languages are supported?', 'ما اللغات المدعومة؟',
             'The interface supports Arabic and English with full RTL support.', 'الواجهة تدعم العربية والإنجليزية مع دعم كامل للكتابة من اليمين.'],
        ];

        foreach ($faqs as [$qEn, $qAr, $aEn, $aAr]) {
            $this->bump('faqs');
            DB::table('faqs')->insert([
                'question_en' => $qEn, 'question_ar' => $qAr,
                'answer_en' => $aEn, 'answer_ar' => $aAr,
                'is_published' => true, 'sort_order' => $order++,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function seedFaqSuggestions(): void
    {
        $now = now();
        $suggestions = [
            ['هل تصدرون شهادات معتمدة رسمياً؟', 'pending'],
            ['Do you offer courses in machine learning?', 'pending'],
            ['كم مدة صلاحية الحساب؟', 'pending'],
            ['Can I get a refund? (المنصّة مجانية)', 'dismissed'],
            ['هل يوجد تطبيق جوال؟', 'converted'],
            ['What payment methods do you accept?', 'dismissed'],
            ['هل يمكن التعلّم دون اتصال بالإنترنت؟', 'pending'],
        ];

        foreach ($suggestions as [$q, $status]) {
            $this->bump('faq_suggestions');
            DB::table('faq_suggestions')->insert([
                'question' => $q, 'status' => $status, 'faq_id' => null,
                'created_at' => $now->copy()->subDays(rand(1, 10)), 'updated_at' => $now,
            ]);
        }
    }

    private function seedAppSettings(): void
    {
        // عدد الأسئلة الشائعة المعروضة على الصفحة الرئيسية (2..5).
        DB::table('app_settings')->updateOrInsert(
            ['key' => 'home_faq_count'],
            ['value' => '5', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  أدوات
    // ═══════════════════════════════════════════════════════════════════════

    private function bump(string $key, int $by = 1): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $by;
    }

    private function printSummary(): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->newLine();
        $this->command->info('✔ MassiveDataSeeder: تمت تعبئة القاعدة ببيانات ضخمة.');
        ksort($this->counts);
        foreach ($this->counts as $table => $count) {
            $this->command->line(sprintf('   %-26s %d', $table, $count));
        }
        $this->command->newLine();
        $this->command->info('حسابات الدخول (كلمة المرور: '.self::PASSWORD.')');
        $this->command->line('   admin@example.com      (admin)');
        $this->command->line('   publisher@example.com  (publisher)');
        $this->command->line('   user@example.com       (student)');
        $this->command->line('   disabled@example.com   (معطّل — لتجربة حالة الإيقاف)');
        $this->command->newLine();
        $this->command->info('ملاحظة: تأكّد أنّ APP_URL يطابق رابط الخادم لعرض الصور/الفيديوهات.');
    }
}
