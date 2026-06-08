<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_exams', function (Blueprint $table) {
            // عدد الأسئلة التي تُسحب عشوائياً من البنك لكل محاولة.
            // NULL = لم يُضبط بعد (الامتحانات القديمة قبل هذه الميزة).
            $table->unsignedSmallInteger('questions_to_serve')->nullable()->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('course_exams', function (Blueprint $table) {
            $table->dropColumn('questions_to_serve');
        });
    }
};
