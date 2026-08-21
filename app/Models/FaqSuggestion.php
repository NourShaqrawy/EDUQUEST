<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqSuggestion extends Model
{
    protected $fillable = [
        'question',
        'status',
        'faq_id',
    ];

    public function faq()
    {
        return $this->belongsTo(Faq::class);
    }
}
