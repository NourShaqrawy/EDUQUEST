<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إشعارات المستخدمين. كل صف يخص مستخدماً واحداً، ويُبثّ عبر القناة user.{user_id}.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('general'); // نوع الإشعار (general, course, exam ...)
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable(); // بيانات إضافية اختيارية (مثل course_id)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
