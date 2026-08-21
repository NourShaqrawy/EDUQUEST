<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // اقتراحات الزوّار: يأتي السؤال بلغة واحدة (يحدّدها المرسِل)، والأدمن يترجمه ويجيب لاحقاً.
        Schema::create('faq_suggestions', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->enum('language', ['en', 'ar']); // لغة السؤال الأصلي كما أرسله الزائر
            $table->enum('status', ['pending', 'converted', 'dismissed'])->default('pending');
            // الـ FAQ الناتج عند تحويل الاقتراح (اختياري)
            $table->foreignId('faq_id')->nullable()->constrained('faqs')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_suggestions');
    }
};
