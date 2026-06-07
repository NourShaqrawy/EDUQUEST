<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * محاولة امتحان واحدة لكل طالب لكل امتحان كورس.
     * الوقت مرجعيّته الخادم: ends_at = started_at + duration. قطع الاتصال لا يوقف العدّاد.
     */
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_exam_id')->constrained('course_exams')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ends_at');
            $table->timestamp('submitted_at')->nullable();
            $table->enum('status', ['in_progress', 'submitted', 'expired'])->default('in_progress');
            $table->decimal('score', 5, 2)->nullable(); // نسبة الامتحان 0..100
            $table->timestamps();

            $table->unique(['user_id', 'course_exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
