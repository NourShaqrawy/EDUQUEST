<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Category ──────────────────────────────────────────────────
        $categoryId = DB::table('categories')->insertGetId([
            'name'        => 'Web Development',
            'description' => 'Courses related to web development',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // publisher user seeded by UsersTableSeeder
        $publisherId = DB::table('users')->where('email', 'publisher@example.com')->value('id');

        // ── Course ────────────────────────────────────────────────────
        $courseId = DB::table('courses')->insertGetId([
            'title'        => 'Laravel API Development',
            'description'  => 'Learn how to build REST APIs with Laravel.',
            'thumbnail'    => 'placeholder/course.jpg',
            'category_id'  => $categoryId,
            'publisher_id' => $publisherId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // ── Course Video ──────────────────────────────────────────────
        $videoId = DB::table('course_videos')->insertGetId([
            'course_id'   => $courseId,
            'title'       => 'Introduction to Routes',
            'description' => 'Understand how routing works in Laravel.',
            'video_path'  => 'placeholder/intro.mp4',
            'duration'    => 300,
            'order'       => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // ── Video Question 1 (appears at 0:30) ────────────────────────
        $q1Id = DB::table('video_questions')->insertGetId([
            'video_id'      => $videoId,
            'question'      => 'Which file contains the API routes in Laravel?',
            'time_in_video' => 30,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::table('video_question_options')->insert([
            ['question_id' => $q1Id, 'option_text' => 'routes/api.php',    'is_correct' => true,  'created_at' => now(), 'updated_at' => now()],
            ['question_id' => $q1Id, 'option_text' => 'routes/web.php',    'is_correct' => false, 'created_at' => now(), 'updated_at' => now()],
            ['question_id' => $q1Id, 'option_text' => 'app/routes.php',    'is_correct' => false, 'created_at' => now(), 'updated_at' => now()],
            ['question_id' => $q1Id, 'option_text' => 'config/routes.php', 'is_correct' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Video Question 2 (appears at 2:00) ────────────────────────
        $q2Id = DB::table('video_questions')->insertGetId([
            'video_id'      => $videoId,
            'question'      => 'Which middleware protects routes with Sanctum?',
            'time_in_video' => 120,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::table('video_question_options')->insert([
            ['question_id' => $q2Id, 'option_text' => 'auth:sanctum',   'is_correct' => true,  'created_at' => now(), 'updated_at' => now()],
            ['question_id' => $q2Id, 'option_text' => 'auth:api',       'is_correct' => false, 'created_at' => now(), 'updated_at' => now()],
            ['question_id' => $q2Id, 'option_text' => 'verified',       'is_correct' => false, 'created_at' => now(), 'updated_at' => now()],
            ['question_id' => $q2Id, 'option_text' => 'role:publisher', 'is_correct' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
