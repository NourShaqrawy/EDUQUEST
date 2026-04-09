<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable; // ← مهم
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // علاقات
    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    

    public function videoQuestionAnswers()
    {
        return $this->hasMany(VideoQuestionAnswer::class);
    }

    public function courseCertificates()
    {
        return $this->hasMany(CourseCertificate::class);
    }
}
