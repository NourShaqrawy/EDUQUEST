<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * السماح بمحاولات متعددة لنفس الطالب على نفس الامتحان (إعادة التقديم عند الرسوب).
     * القيد الفريد السابق كان يمنع أكثر من محاولة واحدة بالكلية.
     */
    public function up(): void
    {
        // MySQL يستخدم القيد الفريد المركّب كـ index للـ FK على user_id (عمود أقصى اليسار).
        // نُضيف index مستقل على user_id أولاً لتحرير القيد الفريد قبل حذفه.
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->index('user_id', 'exam_attempts_user_id_idx');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'course_exam_id']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->unique(['user_id', 'course_exam_id']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex('exam_attempts_user_id_idx');
        });
    }
};
