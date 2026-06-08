<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_questions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('exam_question_id')->constrained('exam_questions')->cascadeOnDelete();
            $table->unsignedSmallInteger('display_order');

            $table->unique(['exam_attempt_id', 'exam_question_id']);
            $table->index(['exam_attempt_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_questions');
    }
};
