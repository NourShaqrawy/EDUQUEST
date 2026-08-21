<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // الزائر لم يعد يحدّد لغة سؤاله — الأدمن هو من يحدّدها عند التحويل.
    public function up(): void
    {
        Schema::table('faq_suggestions', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }

    public function down(): void
    {
        Schema::table('faq_suggestions', function (Blueprint $table) {
            $table->enum('language', ['en', 'ar'])->default('en')->after('question');
        });
    }
};
