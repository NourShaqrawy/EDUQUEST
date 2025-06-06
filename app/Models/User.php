<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'address',
        'role',
        'password', // تم إزالة 'name' لأنه غير موجود في الهيكل المطلوب
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // ---------------------- العلاقات (Relationships) ----------------------

    /**
     * الكورسات التي أنشأها الناشر (One-to-Many)
     */
    public function publishedCourses()
    {
        return $this->hasMany(Course::class, 'publisher_id');
    }

    /**
     * الكورسات المسجل فيها الطالب (Many-to-Many عبر جدول course_enrollments)
     */
    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'course_enrollments', 'student_id', 'course_id')
                    ->withTimestamps();
    }

    /**
     * إجابات التمارين التي قدمها الطالب (One-to-Many)
     */
    public function exerciseSubmissions()
    {
        return $this->hasMany(Exercise_Submissions::class, 'student_id');
    }

    /**
     * تفاعلات الفيديو التي قام بها المستخدم (One-to-Many)
     */
    public function videoReactions()
    {
        return $this->hasMany(Video_Reactions::class, 'user_id');
    }
}
