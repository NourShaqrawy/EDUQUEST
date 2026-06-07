<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * أسئلة امتحان الكورس. لكل سؤال 2..4 خيارات وإجابة صحيحة واحدة (يُفرض في الـ Validation).
     */
    public function up(): void
    {
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('course_exam_id')->constrained('course_exams')->cascadeOnDelete();
            $table->text('question');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_questions');
    }
};
