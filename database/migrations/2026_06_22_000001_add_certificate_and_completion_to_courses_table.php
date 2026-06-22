<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // نوع الكورس: ذو شهادة (true) أو تعريفي بلا شهادة (false). يُحدّده الناشر عند الإنشاء.
            $table->boolean('has_certificate')->default(true)->after('status');

            // حالة اكتمال المحتوى — يتحكم بها الناشر. الطالب لا يرى إلا (completed + approved).
            $table->enum('completion_status', ['ongoing', 'completed'])
                ->default('ongoing')
                ->after('has_certificate');
        });

        // الحفاظ على سلوك البيانات الحالية: الكورسات الموجودة تبقى ظاهرة وتُعامَل كما كانت.
        DB::table('courses')->update([
            'has_certificate'   => true,
            'completion_status' => 'completed',
        ]);
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['has_certificate', 'completion_status']);
        });
    }
};
