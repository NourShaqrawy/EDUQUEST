<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * التقييم النهائي لكل طالب لكل كورس.
     * final = (lessons_percentage × 30%) + (exam_percentage × 70%).
     */
    public function up(): void
    {
        Schema::create('course_results', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->decimal('lessons_percentage', 5, 2)->default(0); // 0..100 (مكوّن الـ 30%)
            $table->decimal('exam_percentage', 5, 2)->default(0);    // 0..100 (مكوّن الـ 70%)
            $table->decimal('final_score', 5, 2)->default(0);        // 0..100
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_results');
    }
};
