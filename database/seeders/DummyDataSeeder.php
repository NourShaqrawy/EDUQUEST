<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\Video;
use App\Models\Exercise;
use App\Models\Exercise_Submissions;
use App\Models\Video_Reactions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. إنشاء المجالات (Categories)
        $categories = [
            [
                'name' => 'برمجة',
                'description' => 'دورات في تطوير البرمجيات وتعلم اللغات البرمجية'
            ],
            [
                'name' => 'تصميم',
                'description' => 'دورات في التصميم الجرافيكي وتصميم الواجهات'
            ],
            [
                'name' => 'أعمال',
                'description' => 'دورات في إدارة الأعمال والتسويق'
            ]
        ];
        Category::insert($categories);

        // 2. إنشاء المستخدمين (Users)
        $users = [
            [
                'first_name' => 'أحمد',
                'last_name' => 'الخالد',
                'email' => 'admin@example.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'address' => 'الرياض'
            ],
            [
                'first_name' => 'سارة',
                'last_name' => 'محمد',
                'email' => 'publisher@example.com',
                'password' => Hash::make('password123'),
                'role' => 'publisher',
                'address' => 'جدة'
            ],
            [
                'first_name' => 'خالد',
                'last_name' => 'علي',
                'email' => 'student1@example.com',
                'password' => Hash::make('password123'),
                'role' => 'student',
                'address' => 'الدمام'
            ],
            [
                'first_name' => 'نور',
                'last_name' => 'حسن',
                'email' => 'student2@example.com',
                'password' => Hash::make('password123'),
                'role' => 'student',
                'address' => 'الخبر'
            ]
        ];
        User::insert($users);

        // 3. إنشاء الكورسات (Courses)
        $courses = [
            [
                'publisher_id' => 2, // سارة (ناشر)
                'category_id' => 1,  // برمجة
                'title' => 'تعلم Laravel من الصفر',
                'description' => 'دورة متكاملة لتعلم إطار العمل Laravel خطوة بخطوة',
                'created_at' => now()
            ],
            [
                'publisher_id' => 2, // سارة (ناشر)
                'category_id' => 2,  // تصميم
                'title' => 'تصميم الويب باستخدام Figma',
                'description' => 'تعلم أساسيات تصميم واجهات المستخدم',
                'created_at' => now()
            ],
            [
                'publisher_id' => 2, // سارة (ناشر)
                'category_id' => 3,  // أعمال
                'title' => 'أساسيات التسويق الرقمي',
                'description' => 'مقدمة شاملة لعالم التسويق الإلكتروني',
                'created_at' => now()
            ]
        ];
        Course::insert($courses);

        // 4. إنشاء الفيديوهات (Videos)
        $videos = [
            // فيديوهات كورس Laravel
            [
                'course_id' => 1,
                'title' => 'مقدمة إلى Laravel',
                'description' => 'شرح أساسيات إطار العمل Laravel',
                'video_url' => 'https://example.com/videos/laravel-intro',
                'video_order' => 1,
                'duration' => 720 // 12 دقيقة
            ],
            [
                'course_id' => 1,
                'title' => 'نموذج MVC في Laravel',
                'description' => 'فهم بنية Model-View-Controller',
                'video_url' => 'https://example.com/videos/laravel-mvc','video_order' => 2,
                'duration' => 900 // 15 دقيقة
            ],
            // فيديوهات كورس Figma
            [
                'course_id' => 2,
                'title' => 'مقدمة إلى Figma',
                'description' => 'تعلم واجهة برنامج Figma',
                'video_url' => 'https://example.com/videos/figma-intro',
                'video_order' => 1,
                'duration' => 600 // 10 دقيقة
            ]
        ];
        Video::insert($videos);

        // 5. إنشاء التمارين (Exercises)
        $exercises = [
            [
                'video_id' => 1, // الفيديو الأول في كورس Laravel
                'pause_time' => 300, // عند الدقيقة 5
                'display_duration' => 120,
                'content' => 'ما هي المكونات الأساسية في Laravel؟'
            ],
            [
                'video_id' => 3, // الفيديو الأول في كورس Figma
                'pause_time' => 240, // عند الدقيقة 4
                'display_duration' => 90,
                'content' => 'ما هي الأدوات الأساسية في Figma؟'
            ]
        ];
        Exercise::insert($exercises);

        // 6. تسجيل الطلاب في الكورسات (Enrollments)
        $student1 = User::find(3); // خالد
        $student1->enrolledCourses()->attach([1, 2]); // مسجل في كورس Laravel و Figma

        $student2 = User::find(4); // نور
        $student2->enrolledCourses()->attach([1]); // مسجلة فقط في كورس Laravel

        // 7. إجابات التمارين (Submissions)
        Exercise_Submissions::insert([
            [
                'student_id' => 3, // خالد
                'exercise_id' => 1, // التمرين الأول
                'score' => 85,
                'submission_date' => now()
            ],
            [
                'student_id' => 4, // نور
                'exercise_id' => 1, // التمرين الأول
                'score' => 92,
                'submission_date' => now()
            ]
        ]);

        // 8. تفاعلات الفيديوهات (Reactions)
        Video_Reactions::insert([
            [
                'video_id' => 1,
                'user_id' => 3, // خالد
                'reaction' => 'like',
                'reacted_at' => now()
            ],
            [
                'video_id' => 3,
                'user_id' => 4, // نور
                'reaction' => 'like',
                'reacted_at' => now()
            ]
        ]);
    }
}
