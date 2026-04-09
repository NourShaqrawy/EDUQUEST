<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_videos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();

            $table->string('title', 255);
            $table->text('description')->nullable();

            // الفيديو الأصلي
            $table->string('video_path', 255);

            // أعمدة الجودات المختلفة
            $table->string('video_144p', 255)->nullable();
            $table->string('video_360p', 255)->nullable();
            $table->string('video_720p', 255)->nullable();

            $table->integer('duration')->default(0);
            $table->integer('order')->default(0);

            $table->index(['course_id', 'order']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_videos');
    }
};
