<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * يضمن أن المستخدم يجيب على كل سؤال مرة واحدة فقط (لا يمكن تكرار/الكتابة فوق الإجابة).
     */
    public function up(): void
    {
        Schema::table('video_question_answers', function (Blueprint $table) {
            $table->unique(['user_id', 'question_id'], 'vqa_user_question_unique');
        });
    }

    public function down(): void
    {
        Schema::table('video_question_answers', function (Blueprint $table) {
            $table->dropUnique('vqa_user_question_unique');
        });
    }
};
