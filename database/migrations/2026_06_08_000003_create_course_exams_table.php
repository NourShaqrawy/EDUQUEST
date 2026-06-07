<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إعدادات امتحان الكورس النهائي (امتحان واحد لكل كورس).
     * لا يُنشر إلا إذا كان عدد الأسئلة ضمن النطاق المسموح (10..35) — يُفرض في طبقة الخدمة.
     */
    public function up(): void
    {
        Schema::create('course_exams', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('course_id')->unique()->constrained('courses')->cascadeOnDelete();
            $table->unsignedInteger('duration_minutes');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_exams');
    }
};
