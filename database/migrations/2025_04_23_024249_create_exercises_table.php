<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->unique()->constrained('videos')->cascadeOnDelete();
            $table->integer('pause_time');
            $table->integer('display_duration');
            // $table->text('content')->nullable();
            // $table->enum('question_type', ['multiple_choice', 'true_false'])->after('video_id');
            // تحويل حقل content إلى json لتخزين الخيارات والإجابة الصحيحة
            $table->json('content')->nullable()->change();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
