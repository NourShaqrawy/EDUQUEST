<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * أحداث مراقبة الامتحان (خروج من ملء الشاشة، نسخ/لصق، مغادرة تبويب ...).
     * client_at: ساعة المتصفح — غير موثوقة، للعرض فقط.
     * created_at: ساعة الخادم — المرجعية الرسمية.
     */
    public function up(): void
    {
        Schema::create('exam_attempt_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->string('type', 50);
            $table->json('meta')->nullable();
            $table->timestamp('client_at')->nullable();   // browser clock — untrusted
            $table->timestamps();                          // created_at = server-authoritative

            $table->index(['exam_attempt_id', 'created_at']); // لتايم‌لاين مراجعة الناشر
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_events');
    }
};
