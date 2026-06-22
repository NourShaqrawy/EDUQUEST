<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_delete_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('publisher_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamps();

            // مستخدم واحد لا يستطيع تقديم أكثر من طلب معلق لنفس الكورس
            $table->unique(['course_id', 'publisher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_delete_requests');
    }
};
