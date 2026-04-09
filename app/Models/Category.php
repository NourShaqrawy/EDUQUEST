<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'name',
    'description',
])]
class Category extends Model
{
    use HasFactory;



    public function courses()
    {
        return $this->hasMany(Course::class);
    }
}
