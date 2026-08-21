<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // الأسئلة الشائعة — ثنائية اللغة إجبارياً (سؤال + إجابة بالعربية والإنكليزية).
        // سؤال واحد => إجابة واحدة (لا جدول منفصل للإجابات).
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question_en');
            $table->string('question_ar');
            $table->text('answer_en');
            $table->text('answer_ar');
            // معروض في الصفحة الرئيسية أم لا. لا يمكن نشره إلا إذا اكتملت الحقول الأربعة (يُفرض في الكنترولر).
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
