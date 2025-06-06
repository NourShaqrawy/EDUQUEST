<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $fillable = ['student_id', 'course_id', 'certificate_code', 'issued_at'];
}
